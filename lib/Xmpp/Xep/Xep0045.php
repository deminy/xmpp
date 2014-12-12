<?php

namespace Xmpp\Xep;

use DOMElement;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Xmpp\Connection;
use Xmpp\Exception\XmppException;
use Xmpp\Iq;
use Xmpp\Presence;

/**
 * The Xmpp\Xep\Xep0045 class.
 */
class Xep0045
{
    /**
     * @var array
     */
    protected $options = array(
        'from'      => null,
        // 'id'     => null,
        'realm'     => null,
        'mucServer' => null,
    );

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param array $options List of options:
     *   # username:
     *   # password:
     *   # host: e.g., example.com
     *   # ssl: Boolean TRUE or FALSE
     *   # port: e.g., 5222
     *   # resource: For heavy loaded server, suggest it make it unique for each call. e.g., "uniqid('', true)".
     *   # mucServer: If not specified, it will query against the XMPP server to get MUC server. Suggesting to provide
     *                it for performance reason.
     * @param LoggerInterface $logger
     */
    public function __construct(array $options, LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        $this->connection = new Connection(
            $options['username'],
            $options['password'],
            $options['host'],
            $options['ssl'],
            $options['port'],
            $options['resource'],
            $this->logger
        );

        // $this->options['id'] = substr($options['username'], 0, strpos($options['username'], '@'));
        $this->options['realm'] = substr($options['username'], strpos($options['username'], '@') + 1);
        $this->options['from']  = $this->getFullUserId(
            substr($options['username'], 0, strpos($options['username'], '@')), // $this->options['id'],
            '' // $options['resource']
        );

        $this->connection->connect();
        $this->connection->authenticate();
        $this->connection->bind();
        $this->connection->establishSession();
        // $this->connection->presence();

        $this->setMucServer(array_key_exists('mucServer', $options) ? $options['mucServer'] : null);
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->connection->disconnect();
    }

    /**
     * @param string $host
     * @return $this
     * @throws XmppException
     */
    public function setMucServer($host = null)
    {
        if (!empty($host)) {
            $this->options['mucServer'] = $host;
        } else {
            if ($this->connection->isMucSupported()) {
                $this->options['mucServer'] = $this->connection->getMucServer();
            } else {
                $this->logger->critical('XMPP server seems not supporting MUC.');

                throw new XmppException('Chatting functionality is not available for now.');
            }
        }

        return $this;
    }

    /**
     * @param string $userId
     * @param string $resource
     * @return string
     */
    public function getFullUserId($userId, $resource = '')
    {
        return ($userId . '@' . $this->options['realm'] . ($resource ? "/{$resource}" : ''));
    }

    /**
     * @param string $roomId
     * @param string $nickname
     * @return string
     */
    public function getFullRoomId($roomId, $nickname = '')
    {
        return ($roomId . '@' . $this->options['mucServer'] . ($nickname ? "/{$nickname}" : ''));
    }

    /**
     * @param int $roomId
     * @param string $roomNickname
     * @return boolean
     * @see http://xmpp.org/extensions/xep-0045.html#createroom
     * @todo test if failed or succeeded.
     * @todo Encode room nickname first before using it; otherwise creating room could fail because of invalid XML.
     */
    public function createRoom($roomId, $roomNickname = '')
    {
        $presence = new Presence();
        $presence
            ->setFrom($this->options['from'])
            ->setTo($this->getFullRoomId($roomId))
            ->initDom(new DOMElement('x', null, 'http://jabber.org/protocol/muc'))
        ;

        $this->connection->getStream()->send($presence);

        $response = $this->connection->waitForServer('*');
        $this->connection->logResponse($response, 'Response when creating chatroom');
    }

    /**
     * @param int $roomId
     * @param string $reason
     * @return boolean
     * @see http://xmpp.org/extensions/xep-0045.html#destroyroom
     */
    public function destroyRoom($roomId, $reason = '')
    {
        $iq = $this->getIq('set')->setTo($this->getFullRoomId($roomId));
        $iq->initQuery(
            'http://jabber.org/protocol/muc#owner',
            'destroy',
            array(
                // 'jid' => $this->getFullRoomId($roomId),
            ),
            $reason
        );

        $this->connection->getStream()->send($iq);

        $response = $this->connection->waitForServer('*');
        $this->connection->logResponse($response, 'Response when destroying chatroom');
    }

    /**
     * @param int $roomId
     * @param string $name
     * @return boolean
     * @todo Not yet implemented.
     */
    public function renameRoom($roomId, $name)
    {
        $this->logger->warn('Changing XMPP chatroom names has not yet been implemented.');
    }

    /**
     * @param int $roomId
     * @param int $userId
     * @param string $reason
     * @return boolean
     * @see http://xmpp.org/extensions/xep-0045.html#grantmember
     * @todo Encode user nickname first before using it; otherwise adding user could fail because of invalid XML.
     */
    public function grantMember($roomId, $userId, $reason = '')
    {
        $iq = $this->getIq('set')->setTo($this->getFullRoomId($roomId));
        $iq->initQuery(
            'http://jabber.org/protocol/muc#admin',
            'item',
            array(
                'affiliation' => 'member',
                'jid'         => $this->getFullUserId($userId),
                // 'nick'     => null,
            ),
            $reason
        );

        $this->connection->getStream()->send($iq);
        $response = $this->connection->waitForServer('iq');
        $this->connection->logResponse($response, 'Response when granting member');
    }

    /**
     * @param int $roomId
     * @param int $userId
     * @param string $reason
     * @return boolean
     * @see http://xmpp.org/extensions/xep-0045.html#revokemember
     */
    public function revokeMember($roomId, $userId, $reason = '')
    {
        $iq = $this->getIq('set')->setTo($this->getFullRoomId($roomId));
        $iq->initQuery(
            'http://jabber.org/protocol/muc#admin',
            'item',
            array(
                'affiliation' => 'none',
                'jid'         => $this->getFullUserId($userId),
            ),
            $reason
        );

        $this->connection->getStream()->send($iq);

        $response = $this->connection->waitForServer('*');
        $this->connection->logResponse($response, 'Response when revoking member');
    }

    /**
     * @param int $roomId
     * @return array
     * @see http://xmpp.org/extensions/xep-0045.html#modifymember
     */
    public function getMemberList($roomId)
    {
        $iq = $this->getIq('get')->setTo($this->getFullRoomId($roomId));
        $iq->initQuery(
            'http://jabber.org/protocol/muc#admin',
            'item',
            array(
                'affiliation' => 'member',
            )
        );

        $this->connection->getStream()->send($iq);

        $response = $this->connection->waitForServer('*');
        $this->connection->logResponse($response, 'Response when getting member list');

        $members = array();
        if ($response instanceof SimpleXMLElement) {
            /**
             * In case when error happens, the response could be like following:
             *
             * <iq from='room_name@host' to='sender@host/resource' type='error' id='4227107239'>
             *     <query xmlns='http://jabber.org/protocol/muc#admin'>
             *         <item affiliation='member'/>
             *     </query>
             *     <error code='404' type='cancel'>
             *         <item-not-found xmlns='urn:ietf:params:xml:ns:xmpp-stanzas'/>
             *         <text xmlns='urn:ietf:params:xml:ns:xmpp-stanzas'>Conference room does not exist</text>
             *     </error>
             * </iq>
             */
            foreach ($response->query->item as $item) {
                if (isset($item['jid'])) {
                    $jid = (string) $item['jid'];
                    $members[substr($jid, 0, strpos($jid, '@'))] = (string) $item['affiliation'];
                }
            }
        }

        return $members;
    }

    /**
     * @param string $type
     * @return Iq
     */
    protected function getIq($type = null)
    {
        $iq = new Iq($this->options);

        if (isset($type)) {
            $iq->setType($type);
        }

        return $iq;
    }

    /**
     * Disconnect from the server.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->getConnection->disconnect();
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
