<?php

use Entities\CustomerNotes;
/*
 * Copyright (C) 2009-2013 Internet Neutral Exchange Association Limited.
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
 * Controller: Customer Notes
 *
 * @author     Barry O'Donovan <barry@opensolutions.ie>
 * @category   IXP
 * @package    IXP_Controller
 * @copyright  Copyright (c) 2009 - 2012, Internet Neutral Exchange Association Ltd
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU GPL V2.0
 */
class CustomerNotesController extends IXP_Controller_AuthRequiredAction
{

    public function ajaxAddAction()
    {
        $this->assertPrivilege( \Entities\User::AUTH_SUPERUSER, true );
        
        $f = new IXP_Form_Customer_Notes();
        $r = [ 'error' => true ];
        
        if( $f->isValid( $_POST ) )
        {
            // locate the customer
            $cust = $this->getD2EM()->getRepository( '\\Entities\\Customer' )->find( $f->getValue( 'custid' ) );

            // if we're editing, we need to fine the note
            if( $f->getValue( 'noteid' ) )
            {
                $isEdit = true;
                $n = $this->getD2EM()->getRepository( '\\Entities\\CustomerNote' )->find( $f->getValue( 'noteid' ) );
            }
            else
            {
                $isEdit = false;
                $n = new \Entities\CustomerNote();
            }
            
            if( $cust && $n )
            {
                $n->setTitle( $f->getValue( 'title' ) );
                $n->setNote( $f->getValue( 'note' ) );
                $n->setPrivate( $f->getValue( 'public' ) == 'makePublic' ? false : true );
                $n->setUpdated( new DateTime() );
                
                if( !$isEdit )
                {
                    $n->setCreated( $n->getUpdated() );
                    $n->setCustomer( $cust );
                    $this->getD2EM()->persist( $n );
                }
                
                // update the user's notes last read so he won't be told his own is new
                $this->getUser()->setPreference( "customer-notes.{$this->getParam( 'custid' )}.last_read", mktime() );
                
                $this->getD2EM()->flush();
                
                $r[ 'error' ] = false;
                $r[ 'noteid' ] = $n->getId();
            }
            else
            {
                $r['error'] = "Invalid customer / note specified.";
                $this->getLogger()->alert( "[ID: {$this->getUser()->getId()}] AJAX Customer Note addition - invalid customer / note specified" );
            }
        }
        
        $this->_helper->json( $r );
    }

    public function ajaxGetAction()
    {
        $r = [ 'error' => true ];
        
        if( $note = $this->getD2EM()->getRepository( '\\Entities\\CustomerNote' )->find( $this->getParam( 'id' ) ) )
        {
            if( $this->getUser()->getPrivs() != \Entities\User::AUTH_SUPERUSER
                && ( $note->getCustomer() != $this->getCustomer() || $note->getPrivate() ) )
            {
                $this->getLogger()->alert(
                    "User {$this->getUser()->getUsername()} tried to access other / private note with ID {$note->getId()}"
                );
            }
            else
            {
                $r = $note->toArray();
                $r['created'] = $r['created']->format( 'Y-m-d H:i' );
                $r['error'] = false;
            }
        }
        
        $this->_helper->json( $r );
    }

    public function ajaxDeleteAction()
    {
        $this->assertPrivilege( \Entities\User::AUTH_SUPERUSER, true );
        
        $r = [ 'error' => true ];
        
        if( $note = $this->getD2EM()->getRepository( '\\Entities\\CustomerNote' )->find( $this->getParam( 'id' ) ) )
        {
            $this->getD2EM()->remove( $note );
            $this->getD2EM()->flush();
            $r = [ 'error' => false ];
        }
        
        $this->_helper->json( $r );
    }
    
    public function ajaxPingAction()
    {
        if( $this->getUser()->getPrivs() == \Entities\User::AUTH_SUPERUSER )
            $custid = $this->getParam( 'custid' );
        else
            $custid = $this->getCustomer()->getId();
        
        // update the last read for this user / customer combination
        if( is_numeric( $custid ) )
        {
            $this->getUser()->setPreference( "customer-notes.{$custid}.last_read", mktime() );
            $this->getD2EM()->flush();
        }
    }
}
