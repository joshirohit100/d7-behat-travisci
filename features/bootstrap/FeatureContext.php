<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Define application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements Context, SnippetAcceptingContext {
  /**
   * Initializes context.
   * Every scenario gets its own context object.
   *
   * @param array $parameters
   *   Context parameters (set them in behat.yml)
   */
  public function __construct(array $parameters = []) {
    // Initialize your context here
  }

    /**
     * The BeforeSuite hook is run before any feature in the suite runs.
     *
     * @BeforeSuite
     */
    public static function prepare($event) {
      if (db_table_exists('watchdog')) {
        db_truncate('watchdog')->execute();
      }
    }
    /**
     * @BeforeScenario
     *
     * Delete the RESTful tokens before every scenario, so user starts as
     * anonymous.
     */
    public function deleteRestfulTokens($event) {
      if (!module_exists('restful_token_auth')) {
        // Module is disabled.
        return;
      }
      if (!$entities = entity_load('restful_token_auth')) {
        // No tokens found.
        return;
      }
      foreach ($entities as $entity) {
        $entity->delete();
      }
    }

    /**
     * The AfterScenario hook is run after executing a scenario.
     *
     * @AfterScenario
     */
    public function afterScenario($event) {

      if (db_table_exists('watchdog')) {
        $log = db_select('watchdog', 'w')
              ->fields('w')
              ->condition('w.type', 'php', '=')
              ->execute()
              ->fetchAll();
        if (!empty($log)) {
          foreach ($log as $error) {
            // Make the substitutions easier to read in the log.
            $error->variables = unserialize($error->variables);
            print_r($error);
          }
          throw new \Exception('PHP errors logged to watchdog in this scenario.');
        }
      }
    }
    /**
     * @AfterStep
     *
     * Take a screen shot after failed steps for Selenium drivers (e.g.
     * PhantomJs).
     */
    public function takeScreenshotAfterFailedStep($event) {
      if ($event->getTestResult()->isPassed()) {
        // Not a failed step.
        return;
      }
      if ($this->getSession()->getDriver() instanceof \Behat\Mink\Driver\Selenium2Driver) {
        $file_name = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'behat-failed-step.png';
        $screenshot = $this->getSession()->getDriver()->getScreenshot();
        file_put_contents($file_name, $screenshot);
        print "Screenshot for failed step created in $file_name";
      }
    }


    /**
   * @Then /^I wait for few seconds$/
   */
  public function iWaitForFewSeconds()
  {
      $this->getSession()->wait(5000,TRUE);
  }


  /**
   * @Then I visit content :title
   *
   * Query the node by title and redirect.
   */
  public function iVisitContent($title) {
    $query = new entityFieldQuery();
    $result = $query
    ->entityCondition('entity_type', 'node')
    ->propertyCondition('title', $title)
    ->propertyCondition('status', NODE_PUBLISHED)
    ->range(0, 1)
    ->execute();
    if (empty($result['node'])) {
      $params = array('@title' => $title);
      throw new Exception(format_string("Node @title not found.", $params));
    }
    $nid = key($result['node']);
    $this->getSession()->visit($this->locatePath('node/' . $nid));
  }
  /**
   * @Then I wait for text :text to :appear
   */
  public function iWaitForText($text, $appear) {
    $this->waitForXpathNode(".//*[contains(normalize-space(string(text())), \"$text\")]", $appear == 'appear');
  }
  /**
   * @Then I wait for css element :element to :appear
   */
  public function iWaitForCssElement($element, $appear) {
    $xpath = $this->getSession()->getSelectorsHandler()->selectorToXpath('css', $element);
    $this->waitForXpathNode($xpath, $appear == 'appear');
  }

  /**
   * Helper function; Execute a function until it return TRUE or timeouts.
   *
   * @param $fn
   *   A callable to invoke.
   * @param int $timeout
   *   The timeout period. Defaults to 10 seconds.
   *
   * @throws Exception
   */
  private function waitFor($fn, $timeout = 10000) {
    $start = microtime(true);
    $end = $start + $timeout / 1000.0;
    while (microtime(true) < $end) {
      if ($fn($this)) {
        return;
      }
    }
    throw new \Exception('waitFor timed out.');
  }
  /**
   * Wait for an element by its XPath to appear or disappear.
   *
   * @param string $xpath
   *   The XPath string.
   * @param bool $appear
   *   Determine if element should appear. Defaults to TRUE.
   *
   * @throws Exception
   */
  private function waitForXpathNode($xpath, $appear = TRUE) {
    $this->waitFor(function($context) use ($xpath, $appear) {
      try {
        $nodes = $context->getSession()->getDriver()->find($xpath);
        if (count($nodes) > 0) {
          $visible = $nodes[0]->isVisible();
          return $appear ? $visible : !$visible;
        }
        return !$appear;
      }
      catch (WebDriver\Exception $e) {
        if ($e->getCode() == WebDriver\Exception::NO_SUCH_ELEMENT) {
          return !$appear;
        }
        throw $e;
      }
    });
  }

//
// Place your definition and hook methods here:
//
//  /**
//   * @Given I have done something with :stuff
//   */
//  public function iHaveDoneSomethingWith($stuff) {
//    doSomethingWith($stuff);
//  }
//

}
