# Summary

Before creating this repository, I've evaluated following XMPP libraries for PHP:

* [Nikita's fork](https://github.com/nikita2206/Xmpp), which was based on [Alexander's code](https://github.com/alexmace/Xmpp).
* [XMPPHP](https://code.google.com/p/xmpphp). There is a better fork made by [Charles](https://github.com/charlycoste/xmpphp).
* [JAXL](https://github.com/jaxl/JAXL).

Unfortunately, none of above ships with what I need. That's the reason I'm creating this repository based on Nikita's
work.

# Features

* Following features were shipped already from [Nikita's fork](https://github.com/nikita2206/Xmpp):
  * Connects to and authenticates (using DIGEST-MD5) against XMPP servers (tested against Openfire and jabberd2)
  * Supports SSL/TLS connections
  * Allows joining of MUC rooms
  * Sends messages to individuals and MUC rooms.
* Following features are added by me (plus various refactoring, etc):
  * Connects to and authenticates using PLAIN.
  * For members-only MUC rooms, provide code to create chatroom, destroy chatroom, add member, revoke member and get member list.

# Sample Code

```php
use Xmpp\Xep\Xep0045;

$roomId = 'YourHouse';
$userId = 'Tom';

$options = array(
    'username' => 'your_username',
    'password' => 'your_password',
    'host'     => 'example.com',
    'ssl'      => true,
    'port'     => 5222,
    'resource' => 'laptop',
);
$xmpp = new Xep0045($options, $logger);

$xmpp->createRoom($roomId);            // Create the room.
$xmpp->grantMember($roomId, $userId);  // Add a member to the room.
$xmpp->getMemberList($roomId);         // Get member list and there should be only one member.
$xmpp->revokeMember($roomId, $userId); // Remove the only member out from the room.
$xmpp->getMemberList($roomId);         // Get member list and there should be nobody in the room.
$xmpp->destroyRoom($roomId);           // Destroy the room.
```

# Known Limitations

* Only part of XEP-0045 protocol extension have been implemented.
* Although code was refactored/written following [FIG PSR standards](http://www.php-fig.org), it was developped and
tested for certain project under certain environment only.

# License

MIT license.
