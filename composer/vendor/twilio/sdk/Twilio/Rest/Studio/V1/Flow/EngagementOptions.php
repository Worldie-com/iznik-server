<?php

/**
 * This code was generated by
 * \ / _    _  _|   _  _
 * | (_)\/(_)(_|\/| |(/_  v1.0.0
 * /       /
 */

namespace Twilio\Rest\Studio\V1\Flow;

use Twilio\Options;
use Twilio\Values;

/**
 * PLEASE NOTE that this class contains beta products that are subject to change. Use them with caution.
 */
abstract class EngagementOptions {
    /**
     * @param array $parameters The parameters
     * @return CreateEngagementOptions Options builder
     */
    public static function create($parameters = Values::NONE) {
        return new CreateEngagementOptions($parameters);
    }
}

class CreateEngagementOptions extends Options {
    /**
     * @param array $parameters The parameters
     */
    public function __construct($parameters = Values::NONE) {
        $this->options['parameters'] = $parameters;
    }

    /**
     * The parameters
     * 
     * @param array $parameters The parameters
     * @return $this Fluent Builder
     */
    public function setParameters($parameters) {
        $this->options['parameters'] = $parameters;
        return $this;
    }

    /**
     * Provide a friendly representation
     * 
     * @return string Machine friendly representation
     */
    public function __toString() {
        $options = array();
        foreach ($this->options as $key => $value) {
            if ($value != Values::NONE) {
                $options[] = "$key=$value";
            }
        }
        return '[Twilio.Studio.V1.CreateEngagementOptions ' . implode(' ', $options) . ']';
    }
}