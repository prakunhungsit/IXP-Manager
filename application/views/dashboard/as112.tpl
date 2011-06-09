{tmplinclude file="header.tpl" pageTitle="IXP Manager :: Member Dashboard"}

<div class="yui-g">

<div id="content">

<table class="adminheading" border="0">
<tr>
    <th class="Switch">
        Details of the INEX AS112 Service
    </th>
</tr>
</table>

{tmplinclude file="message.tpl"}

<div id='ajaxMessage'></div>

<div id="overviewMessage">
    {if isset( $as112JustEnabled ) and $as112JustEnabled}
        <div class="message message-success">
            New sessions for INEX's AS112 service will be enabled for you within the next 12 hours.
            Please see configuration details below.
        </div>
    {elseif $as112Enabled}
        <div class="message message-success">
            You are enabled to use INEX's AS112 service. Please see configuration details below.
        </div>
    {elseif $rsEnabled and not $as112Enabled}
        <div class="message message-info">
	        There are no bilateral BGP sessions configured for you on the AS112 server. However, as
	        you have route server sessions, you receive the AS112 prefixes via this. If you would like
	        to additionally enable bilateral peering, please
	        <a href="{genUrl controller="dashboard" action="as112" enable="1"}">click here to have
	        our provisioning system create the sessions</a> for you.
	    </div>
    {else} *}
	    <div class="message message-alert">
	        You are not enabled to use INEX's AS112 service. Please
            <a href="{genUrl controller="dashboard" action="as112" enable="1"}">click here to have
            our provisioning system create the sessions</a> for you.
	    </div>
    {/if}
</div>



<h3>Overview</h3>

<p>
From <a href="http://public.as112.net/">http://public.as112.net/</a>:
</p>

<blockquote>

<p>
Because most answers generated by the Internet's root name server system are negative,
and many of those negative answers are in response to PTR queries for RFC1918, dynamic
DNS updates and other ambiguous addresses, as follows:
</p>

<ul>
    <li> 10.0.0.0/8</li>
    <li> 172.16.0.0/12</li>
    <li> 169.254.0.0/16</li>
    <li> 192.168.0.0/16</li>
</ul>

<p>
There are now separate (non-root) servers for these queries [such as INEX's AS112 service].
</p>

<p>
As a way to distribute the load across the Internet for RFC1918-related queries, we use
IPv4 anycast addressing. The address block is 192.175.48.0/24 and its origin AS is 112.
This address block is advertised from multiple points around the Internet, and these
distributed servers coordinate their responses and back end statistical analyses.
</p>
</blockquote>

<p>
For the benefit of its members, INEX hosts an AS112 nameserver which answers bogus
requests to private IP address space.  This service is available as a regular peering
host on both INEX peering LANs.  Its IP addreses are: <code>193.242.111.6</code> and
<code>194.88.240.6</code>.
</p>

<h3>Configuration Details</h3>

<p>
For Cisco routers, you will need something like the following example BGP configuration for peering LAN #1:
</p>

<pre>
    router bgp 99999

     ! INEX Peering LAN #1

     neighbor 193.242.111.6 remote-as 112
     neighbor 193.242.111.6 description INEX AS112 Service
     address-family ipv4
     neighbor 193.242.111.6 password s00persekr1t
     neighbor 193.242.111.6 activate
     neighbor 193.242.111.6 filter-list 100 out

</pre>

<p>
You should also use <code>route-maps</code> (or <code>distribute-lists</code>) to control
outgoing prefix announcements to allow only the prefixes which you indend to announce.
</p>

<h3>More Information on the AS112 Project</h3>

<p>
Please see <a href="http://public.as112.net/">http://public.as112.net/</a> for more information
on the AS112 project, <em>The Nameservers at the End of the Universe</em>.
</p>

</div>
</div>

{tmplinclude file="footer.tpl"}
