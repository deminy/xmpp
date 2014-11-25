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
    protected $from;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $to;

    /**
     * @var string
     */
    protected $type;

    /**
     * Class constructor, sets up common class variables.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->from = array_key_exists('from', $options) ? $options['from'] : null;
        $this->id   = array_key_exists('id', $options)   ? $options['id']   : null;
        $this->to   = array_key_exists('to', $options)   ? $options['to']   : null;
        $this->type = array_key_exists('type', $options) ? $options['type'] : null;
    }

    /**
     * @param string $from
     * @return $this
     */
    public function setFrom($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Returns the JID of the sender of the stanza.
     *
     * @return string
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Returns the "id" of the stanza.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $to
     * @return $this
     */
    public function setTo($to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Returns who the JID of who the stanza was sent to.
     *
     * @return string
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
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
}
