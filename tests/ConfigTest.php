<?php
namespace Scoutapm\Tests;

use \Scoutapm\Agent;
use \Scoutapm\Config;
use \PHPUnit\Framework\TestCase;

/**
 * Test Case for @see \Scoutapm\Config
 */
final class ConfigTest extends TestCase {
  public function testGetFallsBackToDefaults() {
    $config = new Config();

    // Provided by the DefaultConfig
    $this->assertEquals('1.0', $config->get("api_version"));
  }

  public function testUserSettingsOverridesDefaults() {
    $config = new Config();
    $config->set("api_version", "viauserconf");
      
    $this->assertEquals("viauserconf", $config->get("api_version"));
  }

  public function testEnvOverridesAll()
  {
    $config = new Config();

    // Set a user config. This won't be looked up
    $config->set("api_version", "viauserconf");

    // And set the env var
    putEnv("SCOUT_API_VERSION=viaenvvar");
      
    $this->assertEquals("viaenvvar", $config->get("api_version"));
  }
}
