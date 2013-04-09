<?php

/*
 * Copyright (C) 2009-2011 Internet Neutral Exchange Association Limited.
 * All Rights Reserved.
 *
 * This file is part of IXP Manager.
 *
 * IXP Manager is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, version v2.0 of the License.
 *
 * IXP Manager is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License v2.0
 * along with IXP Manager.  If not, see:
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */


/**
 * Controller: Manage contacts
 *
 * @author     Barry O'Donovan <barry@opensolutions.ie>
 * @category   IXP
 * @package    IXP_Controller
 * @copyright  Copyright (c) 2009 - 2012, Internet Neutral Exchange Association Ltd
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU GPL V2.0
 */
class ContactController extends IXP_Controller_FrontEnd
{
    
    /**
     * This function sets up the frontend controller
     */
    protected function _feInit()
    {
        $this->assertPrivilege( \Entities\User::AUTH_SUPERUSER );
        
        $this->view->feParams = $this->_feParams = (object)[
            'entity'        => '\\Entities\\Contact',
            'form'          => 'IXP_Form_Contact',
            'pagetitle'     => 'Contacts',
        
            'titleSingular' => 'Contact',
            'nameSingular'  => 'a contact',
        
            'defaultAction' => 'list',                    // OPTIONAL; defaults to 'list'
        
            'listOrderBy'    => 'name',
            'listOrderByDir' => 'ASC',
            
            'addWhenEmpty'   => false
        ];
        
        switch( $this->getUser()->getPrivs() )
        {
            case \Entities\User::AUTH_SUPERUSER:
                
                $this->_feParams->listColumns = [
                
                    'id'        => [ 'title' => 'UID', 'display' => false ],
                
                    'customer'  => [
                        'title'      => 'Customer',
                        'type'       => self::$FE_COL_TYPES[ 'HAS_ONE' ],
                        'controller' => 'customer',
                        'action'     => 'overview',
                        'idField'    => 'custid'
                    ],
                
                    'name'      => 'Name',
                    'position'  => 'Position',
                    'email'     => 'Email',
                    'phone'     => 'Phone',
                    'mobile'    => 'Mobile',
        
                    'created'       => [
                        'title'     => 'Created',
                        'type'      => self::$FE_COL_TYPES[ 'DATETIME' ]
                    ]
                ];
                break;
        
            case \Entities\User::AUTH_CUSTADMIN:
                $this->_feParams->pagetitle = 'Contact Admin for ' . $this->getUser()->getCustomer()->getName();
        
                $this->_feParams->listColumns = [
                ];
                break;
        
            default:
                $this->redirectAndEnsureDie( 'error/insufficient-permissions' );
        }
        
        // display the same information in the view as the list
        $this->_feParams->viewColumns = $this->_feParams->listColumns;
    }


    /**
     * Provide array of users for the listAction and viewAction
     *
     * @param int $id The `id` of the row to load for `viewAction`. `null` if `listAction`
     */
    protected function listGetData( $id = null )
    {
        $qb = $this->getD2EM()->createQueryBuilder()
            ->select( 'c.id as id, c.name as name, c.email as email, c.phone AS phone, c.mobile AS mobile,
                c.facilityaccess AS facilityaccess, c.mayauthorize AS mayauthorize,
                c.lastupdated AS lastupdated, c.lastupdatedby AS lastupdatedby, c.position AS position,
                c.creator AS creator, c.created AS created, cust.name AS customer, cust.id AS custid'
            )
            ->from( '\\Entities\\Contact', 'c' )
            ->leftJoin( 'c.Customer', 'cust' );
        
        if( $this->getParam( "cgid", false ) )
        {
            $qb->leftJoin( "c.Groups", "cg" )
                ->andWhere( "cg.id = ?2" )
                ->setParameter( 2, $this->getParam( "cgid" ) );
            
            $this->view->group = $this->getD2EM()->getRepository( "\\Entities\\ContactGroup" )->find( $this->getParam( "cgid" ) );
        }

        if( $this->getUser()->getPrivs() != \Entities\User::AUTH_SUPERUSER )
            $qb->where( 'c.Customer = :cust' )->setParameter( 'cust', $this->getUser()->getCustomer() );
        
        if( isset( $this->_feParams->listOrderBy ) )
            $qb->orderBy( $this->_feParams->listOrderBy, isset( $this->_feParams->listOrderByDir ) ? $this->_feParams->listOrderByDir : 'ASC' );
    
        if( $id !== null )
            $qb->andWhere( 'c.id = :id' )->setParameter( 'id', $id );
    
        return $qb->getQuery()->getResult();
    }
    
    
    /**
     * Gets the ID of the object for editing - which, by default, returns the id parameter from the request
     *
     * @return int|false
     */
    protected function editResolveId()
    {
        if( $this->_getParam( 'id', false ) )
            return $this->_getParam( 'id' );
        else if( $this->_getParam( 'uid', false ) )
        {
            if( $user = $this->getD2EM()->getRepository( "\\Entities\\User" )->find( $this->getParam( "uid" ) ) )
                return $user->getContact()->getId();
        }

        $this->addMessage( 'The requested contact / user does not exist', OSS_Message::ERROR );
        $this->redirect();
    }
    
    /**
     *
     * @param IXP_Form_Contact $form The form object
     * @param \Entities\Contact $object The Doctrine2 entity (being edited or blank for add)
     * @param bool $isEdit True of we are editing an object, false otherwise
     * @param array $options Options passed onto Zend_Form
     * @param string $cancelLocation Where to redirect to if 'Cancal' is clicked
     * @return void
     */
    protected function formPostProcess( $form, $object, $isEdit, $options = null, $cancelLocation = null )
    {
        $this->view->groups     = $this->getD2EM()->getRepository( "\\Entities\\ContactGroup" )->getGroupNamesTypeArray();
        $this->view->jsonGroups = json_encode( $this->view->groups );
        
        // redirect back to whence we came on form submission
        if( $this->getParam( "uid", false ) )
            $form->setAction( OSS_Utils::genUrl( 'contact', ( $isEdit ? 'edit' : 'add' ), false, [ 'user' => true ] ) );

        if( $isEdit )
        {
            $form->getElement( 'custid' )->setValue( $object->getCustomer()->getId() );
            $this->view->contactGroups = $this->getD2R( "\\Entities\\ContactGroup" )->getGroupNamesTypeArray( false, $object->getId() );
        }
        else if( $this->getParam( 'custid', false ) && ( $cust = $this->getD2R( '\\Entities\\Customer' )->find( $this->getParam( 'custid' ) ) ) )
        {
            $form->getElement( 'custid' )->setValue( $cust->getId() );
        }
        
        if( $object->getUser() )
        {
            $form->getElement( 'login'    )->setValue( 1 );
            $form->getElement( 'username' )->setValue( $object->getUser()->getUsername() );
            $form->getElement( 'password' )->setValue( $object->getUser()->getPassword() );
            $form->getElement( 'privs'    )->setValue( $object->getUser()->getPrivs() );
            $form->getElement( 'disabled' )->setValue( $object->getUser()->getDisabled() );
        }
        else
        {
            $form->getElement( 'password' )->setValue( OSS_String::random( 12 ) );
            $form->getElement( 'username' )->addValidator( 'OSSDoctrine2Uniqueness', true,
                [ 'entity' => '\\Entities\\User', 'property' => 'username' ]
            );
        }
        
        switch( $this->getUser()->getPrivs() )
        {
            case \Entities\User::AUTH_SUPERUSER:
                $form->getElement( 'username' )->removeValidator( 'stringLength' );
                break;
                
            case \Entities\User::AUTH_CUSTADMIN:
                $form->removeElement( 'password' );
                $form->removeElement( 'privs' );
                $form->removeElement( 'custid' );
                
                if( $isEdit )
                    $form->getElement( 'username' )->setAttrib( 'readonly', 'readonly' );
                break;

            default:
                throw new OSS_Exception( 'Unhandled user type / security issues' );
        }
    }
    
    
    /**
     * You can add `OSS_Message`s here and redirect to a custom destination after a
     * successful add / edit operation.
     *
     * By default it returns `false`.
     *
     * On `false`, the default action (`index`) is called and a standard success message is displayed.
     *
     *
     * @param OSS_Form $form The form object
     * @param object $object The Doctrine2 entity (being edited or blank for add)
     * @param bool $isEdit True of we are editing an object, false otherwise
     * @return bool `false` for standard message and redirection, otherwise redirect within this function
     */
    protected function addDestinationOnSuccess( $form, $object, $isEdit  )
    {
        $this->addMessage( 'Contact successfully ' . ( $isEdit ? ' edited.' : ' added.' ), OSS_Message::SUCCESS );
        
        if( $this->getParam( 'user', false ) )
            $this->redirect( 'customer/overview/tab/users/id/' . $object->getCustomer()->getId() );
        else
            $this->redirect( 'customer/overview/tab/contacts/id/' . $object->getCustomer()->getId() );
    }

    /**
     * Function which can be over-ridden to perform any pre-deletion tasks
     *
     * You can stop the deletion by returning false but you should also add a
     * message to explain why.
     *
     * @param object $object The Doctrine2 entity to delete
     * @return bool Return false to stop / cancel the deletion
     */
    protected function preDelete( $object )
    {
        if( $this->getUser()->getPrivs() != \Entities\User::AUTH_SUPERUSER )
        {
            if( $object->getCustomer() != $this->getUser()->getCustomer() )
            {
                $this->getLogger()->notice( "{$this->getUser()->getUsername()} tried to delete other customer user {$object->getUser()->getUsername()}" );
                $this->addMessage( 'You are not authorised to delete this user. The administrators have been notified.' );
                return false;
            }
        }
            
        if( $object->getUser() )
            $this->_deleteUser( $object );
        
        // keep the customer ID for redirection on success
        $this->getSessionNamespace()->ixp_contact_delete_custid = $object->getCustomer()->getId();
        return true;
    }
    
    /**
     * Do the heavy lifting for deleting a user
     *
     * @param \Entities\Contact $contact The contact entity
     */
    private function _deleteUser( $contact )
    {
        $user = $contact->getUser();
        
        // delete all the user's preferences
        foreach( $user->getPreferences() as $pref )
        {
            $user->removePreference( $pref );
            $this->getD2EM()->remove( $pref );
        }
        
        // clear the user from the contact and remove the user then
        $contact->unsetUser();
        $this->getD2EM()->remove( $user );
        
        // in case the user is currently logged in:
        $this->clearUserFromCache( $user->getId() );
        
        $this->getLogger()->info( "{$this->getUser()->getUsername()} deleted user {$user->getUsername()}" );
    }
    
    /**
     * You can add `OSS_Message`s here and redirect to a custom destination after a
     * successful deletion operation.
     *
     * By default it returns `false`.
     *
     * On `false`, the default action (`index`) is called and a standard success message is displayed.
     *
     * @return bool `false` for standard message and redirection, otherwise redirect within this function
     */
    protected function deleteDestinationOnSuccess()
    {
        // retrieve the customer ID
        if( $custid = $this->getSessionNamespace()->ixp_contact_delete_custid )
        {
            unset( $this->getSessionNamespace()->ixp_contact_delete_custid );
            
            $this->addMessage( 'Contact successfully deleted', OSS_Message::SUCCESS );
            $this->redirect( 'customer/overview/tab/contacts/id/' . $custid );
        }
        
        return false;
    }
    
    /**
     * Prevalidation hook that can be overridden by subclasses for add and edit.
     *
     * This is called if the user POSTs a form just before the form is validated by Zend
     *
     * @param OSS_Form $form The Send form object
     * @param object $object The Doctrine2 entity (being edited or blank for add)
     * @param bool $isEdit True if we are editing, otherwise false
     * @return bool If false, the form is not validated or processed
     */
    protected function addPreValidate( $form, $object, $isEdit )
    {

        if( $isEdit && $this->getUser()->getPrivs() != \Entities\User::AUTH_SUPERUSER )
        {
            if( $this->getUser()->getCustomer() != $object->getCustomer() )
            {
                $this->addMessage( 'Illegal attempt to edit a user not under your control. The security team have been notified.' );
                $this->getLogger()->alert( "User {$this->getUser()->getUsername()} illegally tried to edit {$object->getName()}" );
                $this->redirect();
            }
        }
        
        if( isset( $_POST['login'] ) && $_POST['login'] == '1' )
        {
            $form->getElement( "username" )->setRequired( true );
            $form->getElement( "password" )->setRequired( true );
            $form->getElement( "privs"    )->setRequired( true );
        }
        else
            $_POST['login'] = 0;
        

        $this->view->contactGroups = $this->_postedGroupsToArray();
        
        return true;
    }

    
    /**
     * Process submitted groups (and roles) into an array
     *
     * @return array Array of submitted contact groups
     */
    private function _postedGroupsToArray()
    {
        $groups = [];
        foreach( [ 'role', 'group' ] as $groupType )
        {
            if( isset( $_POST[ $groupType ] ) )
            {
                foreach( $_POST[ $groupType ] as $cgid )
                {
                    if( $cg = $this->getD2R( "\\Entities\\ContactGroup" )->find( $cgid ) )
                        $groups[ $cg->getType() ][$cgid] = [ "id" => $cgid ];
                }
            }
        }
        
        return $groups;
    }
    
    /**
     *
     * @param IXP_Form_Contact $form The form object
     * @param \Entities\Contact $object The Doctrine2 entity (being edited or blank for add)
     * @param bool $isEdit True of we are editing an object, false otherwise
     * @return bool If false, the form is not processed
     */
    protected function addPostValidate( $form, $object, $isEdit )
    {
        if( $this->getUser()->getPrivs() == \Entities\User::AUTH_SUPERUSER )
            $object->setCustomer( $this->getD2R( '\\Entities\\Customer' )->find( $form->getElement( 'custid' )->getValue() ) );
        else
            $object->setCustomer( $this->getUser()->getCustomer() );
    
        if( !$isEdit )
        {
            $object->setCreated( new DateTime() );
            $object->setCreator( $this->getUser()->getUsername() );
        }
        
        $object->setLastupdated( new DateTime() );
        $object->setLastupdatedby( $this->getUser()->getId() );
        
        $this->_processUser( $form, $object );

        // let the group processor have the final say as to whether post validation
        // passes or not
        return $this->_setContactGroups( $object );
    }
    
    /**
     * Process submitted groups (and roles) for this contact and update the relationships
     *
     * @param \Entities\Contact $contact
     * @return boolean
     */
    private function _setContactGroups( $contact )
    {
        $groups = [];
        
        foreach( [ 'role', 'group' ] as $groupType )
        {
            foreach( $form->getValue( $groupType ) as $cgid )
            {
                if( $group = $this->getD2R( "\\Entities\\ContactGroup" )->find( $cgid ) )
                {
                    if( $group->getLimitedTo() != 0 )
                    {
                        $contactsWithGroupForCustomer = $this->getD2R( "\\Entities\\ContactGroup" )->countForCustomer( $contact->getCustomer(), $cgid );
        
                        if( !$contact->getGroups()->contains( $group ) && $group->getLimitedTo() <= $contactsWithGroupForCustomer )
                        {
                            $this->addMessage( "Contact group {$group->getName()} has a limited membership and is full.", OSS_Message::WARNING );
                            return false;
                        }
                    }
        
                    if( !$contact->getGroups()->contains( $group ) )
                    {
                        $contact->addGroup( $group );
                        $group->addContact( $contact );
                    }
        
                    $groups[] = $group;
                }
            }
        }
        
        foreach( $contact->getGroups() as $key => $group )
        {
            if( !in_array( $group, $groups ) )
            {
                $contact->getGroups()->remove( $key );
            }
        }
        
        return true;
    }
    
    
    /**
     *
     * @param IXP_Form_User $form The form object
     * @param \Entities\User $object The Doctrine2 entity (being edited or blank for add)
     * @param bool $isEdit True of we are editing an object, false otherwise
     * @return void
     */
    protected function addPostFlush( $form, $object, $isEdit )
    {
        if( isset( $this->_feParams->userStatus ) && $this->_feParams->userStatus == "created" )
        {
            $this->view->newuser = $object->getUser();
            $this->sendWelcomeEmail( $object->getUser() );
        }

        return true;
    }
    
    
     /**
      * Creates/updates/deletes the user for a contact when adding / editing a contact
      *
      * @param IXP_Form_Contact $form The form object
      * @param \Entities\Contact $contact The Doctrine2 entity (being edited or blank for add)
      */
    private function _processUser( $form, $contact )
    {
        if( $form->getValue( "login" ) )
        {
            // the contact has a user already or one needs to be created
            
            if( !( $user = $contact->getUser() ) )
            {
                $user = new \Entities\User();
                $this->getD2EM()->persist( $user );
                $contact->setUser( $user );
                
                $user->setCreated( new DateTime() );
                $user->setCreator( $this->getUser()->getUsername() );
                $user->setCustomer( $contact->getCustomer() );
            }
                
            $user->setDisabled( $form->getValue( "disabled" ) );
            $user->setEmail( $form->getValue( "email" ) );
            $user->setLastupdated( new DateTime() );
            $user->setLastupdatedby( $this->getUser()->getId() );
            
            if( $this->getUser()->getPrivs() == \Entities\User::AUTH_CUSTADMIN )
            {
                $user->setParent( $this->getUser() );
                $user->setPrivs( \Entities\User::AUTH_CUSTUSER );
                $user->setPassword( OSS_String::random( 16 ) );
            }
            else
            {
                $user->setUsername( $form->getValue( "username" ) );
                $user->setPassword( $form->getValue( "password" ) );
                $user->setPrivs( $form->getValue(    "privs" ) );
                
                try
                {
                    $user->setParent(
                        $this->getD2EM()->createQuery(
                            'SELECT u FROM \\Entities\\User u WHERE u.privs = ?1 AND u.Customer = ?2'
                        )
                        ->setParameter( 1, \Entities\User::AUTH_CUSTADMIN )
                        ->setParameter( 2, $user->getCustomer() )
                        ->setMaxResults( 1 )
                        ->getSingleResult()
                    );
                }
                catch( \Doctrine\ORM\NoResultException $e )
                {
                    $user->setParent( $user );
                }
            }
                
            $this->getLogger()->info( "{$this->getUser()->getUsername()} created user {$user->getUsername()}" );
            $this->_feParams->userStatus = "created";
        }
        else // !$form->getValue( "login" )
        {
            if( $contact->getUser() )
                $this->_deleteUser( $contact );
        }
    }
    
    
    /**
     * Send a welcome email to a new user
     *
     * @param \Entities\User $user The recipient of the email
     * @return bool True if the mail was sent successfully
     */
    private function sendWelcomeEmail( $user )
    {
        try
        {
            $mail = $this->getMailer();
            $mail->setFrom( $this->_options['identity']['email'], $this->_options['identity']['name'] )
                ->setSubject( $this->_options['identity']['sitename'] . ' - ' . _( 'Your Access Details' ) )
                ->addTo( $user->getEmail(), $user->getUsername() )
                ->setBodyHtml( $this->view->render( 'user/email/html/welcome.phtml' ) )
                ->send();
        }
        catch( Zend_Mail_Exception $e )
        {
            $this->getLogger()->alert( "Could not send welcome email for new user!\n\n" . $e->toString() );
            return false;
        }
        
        return true;
    }
    
}
