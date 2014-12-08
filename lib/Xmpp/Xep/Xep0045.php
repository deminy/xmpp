<?php

namespace Xmpp\Xep;

use DOMElement;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Xmpp\Connection;
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
     * @var Logger
     */
    protected $logger;

    /**
     * @param array $options
     * @param Logger $logger
     * @throws Exception
     */
    public function __construct(array $options, Logger $logger = null)
    {
        if (empty($logger)) {
            $logger = new Logger('xmpp');
            $logger->pushHandler(new NullHandler());
        }
        $this->logger = $logger;

        $this->connection = new Connection(
            $options['username'],
            $options['password'],
            $options['host'],     // example.com
            $options['ssl'],      // Boolean TRUE or FALSE.
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
        $this->connection->presence();

        if ($this->connection->isMucSupported()) {
            $this->options['mucServer'] = $this->connection->getMucServer();
        } else {
            $this->logger->critical('XMPP server seems not supporting MUC.');

            throw new Exception('Chatting functionality is not available for now.');
        }
    }

    /**
     * @param string $userId
     * @param string $resource
     * @return string
     * @todo Rename $nickname to something more properly.
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

        $this->connection->getCurrentStream()->send($presence);

        $response = $this->connection->waitForServer('*');
        $this->logger->debug('Response when creating chatroom: ' . $response);
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
                'jid' => $this->getFullRoomId($roomId),
            ),
            $reason
        );

        $this->connection->getCurrentStream()->send($iq);

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

        $this->connection->getCurrentStream()->send($iq);

        usleep(300000); //TODO: wait for 0.3 second; fix it.
        $response = $this->connection->waitForServer('iq');
        $this->logger->debug('Response: ' . $response);
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

        $this->connection->getCurrentStream()->send($iq);

        $response = $this->connection->waitForServer('*');
        $this->logger->debug('Response: ' . $response);
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

        $this->connection->getCurrentStream()->send($iq);

        $response = $this->connection->waitForServer('*');

        $members = array();
        if (!empty($response)) {
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
}
