<?php

namespace Scoutapm;

/**
 * Class IgnoredEndpoints
 */
class IgnoredEndpoints
{
    private $agent;
    private $config;

    public function __construct($agent)
    {
        $this->agent = $agent;
        $this->config = $agent->getConfig();
    }
    
    public function ignored(string $url) : bool
    {
        $ignored = $this->config->get("ignore");
        if ($ignored == null) {
            return false;
        }

        foreach ($ignored as $ignore) {
            if (substr($url, 0, strlen($ignore)) === $ignore) {
                return true;
            }
        }
        
        // None Matched
        return false;
    }
}
