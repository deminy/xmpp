<?php

namespace Xmpp;

/**
 * Abstract class for representing Xmpp Stanzas.
 */
abstract class Stanza
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $_from;

    /**
     * @var string
     */
    protected $_to;

    /**
     * @var string
     */
    protected $_id;

    /**
     * Class constructor, sets up common class variables.
     *
     * @param SimpleXMLElement $stanza The XML itself for the stanza.
     */
    public function __construct(SimpleXMLElement $stanza)
    {

        if (isset($stanza['from'])) {
            $this->_from = (string) $stanza['from'];
        }

        if (isset($stanza['to'])) {
            $this->_to = (string) $stanza['to'];
        }

        if (isset($stanza['id'])) {
            $this->_id = (string) $stanza['id'];
        }
    }

    /**
     * Returns the JID of the sender of the stanza.
     *
     * @return string
     */
    public function getFrom()
    {
        return $this->_from;
    }

    /**
     * Returns who the JID of who the stanza was sent to.
     *
     * @return string
     */
    public function getTo()
    {
        return $this->_to;
    }

    /**
     * Returns the value of the "type" attribute on the stanza.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the "id" of the stanza.
     *
     * @return string
     */
    public function getId()
    {
        return $this->_id;
    }
}
