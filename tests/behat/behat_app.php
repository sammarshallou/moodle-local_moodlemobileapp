<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');
require_once(__DIR__ . '/behat_app_helper.php');

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ExpectationException;

/**
 * Moodle App steps definitions.
 *
 * @package core
 * @category test
 * @copyright 2018 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_app extends behat_app_helper {

    /** @var string URL for running Ionic server */
    protected $ionicurl = '';

    /** @var array Config overrides */
    protected $appconfig = ['disableUserTours' => true];

    protected $windowsize = '360x720';

    /**
     * Opens the Moodle App in the browser and optionally logs in.
     *
     * @When I enter the app
     * @Given I entered the app as :username
     * @throws DriverException Issue with configuration or feature file
     * @throws dml_exception Problem with Moodle setup
     * @throws ExpectationException Problem with resizing window
     */
    public function i_enter_the_app(string $username = null) {
        $this->i_launch_the_app();

        if (!is_null($username)) {
            $this->open_moodleapp_custom_login_url($username);

            return;
        }

        $this->enter_site();
    }

    /**
     * Check whether the current page is the login form.
     */
    protected function is_in_login_page(): bool {
        $page = $this->getSession()->getPage();
        $logininput = $page->find('xpath', '//page-core-login-site//input[@name="url"]');

        return !is_null($logininput);
    }

    /**
     * Opens the Moodle App in the browser.
     *
     * @When I launch the app :runtime
     * @When I launch the app
     * @throws DriverException Issue with configuration or feature file
     * @throws dml_exception Problem with Moodle setup
     * @throws ExpectationException Problem with resizing window
     */
    public function i_launch_the_app(string $runtime = '') {
        // Check the app tag was set.
        if (!$this->has_tag('app')) {
            throw new DriverException('Requires @app tag on scenario or feature.');
        }

        // Go to page and prepare browser for app.
        $this->prepare_browser(['skiponboarding' => empty($runtime)]);
    }

    /**
     * @Then I wait the app to restart
     */
    public function i_wait_the_app_to_restart() {
        // Wait window to reload.
        $this->spin(function() {
            $result = $this->evaluate_script("return !window.behat;");

            if (!$result) {
                throw new DriverException('Window is not reloading properly.');
            }

            return true;
        });

        // Prepare testing runtime again.
        $this->prepare_browser(['restart' => false]);
    }

    /**
     * Finds elements in the app.
     *
     * @Then /^I should( not)? find (".+")( inside the .+)? in the app$/
     */
    public function i_find_in_the_app(bool $not, string $locator, string $containerName = '') {
        $locator = $this->parse_element_locator($locator);
        if (!empty($containerName)) {
            preg_match('/^ inside the (.+)$/', $containerName, $matches);
            $containerName = $matches[1];
        }
        $containerName = json_encode($containerName);

        $this->spin(function() use ($not, $locator, $containerName) {
            $result = $this->evaluate_script("return window.behat.find($locator, $containerName);");

            if ($not && $result === 'OK') {
                throw new DriverException('Error, found an item that should not be found');
            }

            if (!$not && $result !== 'OK') {
                throw new DriverException('Error finding item - ' . $result);
            }

            return true;
        });

        $this->wait_for_pending_js();
    }

    /**
     * Scroll to an element in the app.
     *
     * @When /^I scroll to (".+") in the app$/
     * @param string $locator
     */
    public function i_scroll_to_in_the_app(string $locator) {
        $locator = $this->parse_element_locator($locator);

        $this->spin(function() use ($locator) {
            $result = $this->evaluate_script("return window.behat.scrollTo($locator);");

            if ($result !== 'OK') {
                throw new DriverException('Error finding item - ' . $result);
            }

            return true;
        });

        $this->wait_for_pending_js();

        // Wait scroll animation to finish.
        $this->getSession()->wait(300);
    }

    /**
     * Load more items in a list with an infinite loader.
     *
     * @When /^I (should not be able to )?load more items in the app$/
     * @param bool $not
     */
    public function i_load_more_items_in_the_app(bool $not = false) {
        $this->spin(function() use ($not) {
            $result = $this->evaluate_async_script('return window.behat.loadMoreItems();');

            if ($not && $result !== 'ERROR: All items are already loaded.') {
                throw new DriverException('It should not have been possible to load more items');
            }

            if (!$not && $result !== 'OK') {
                throw new DriverException('Error loading more items - ' . $result);
            }

            return true;
        });

        $this->wait_for_pending_js();
    }

    /**
     * Trigger swipe gesture.
     *
     * @When /^I swipe to the (left|right) in the app$/
     * @param string $direction
     */
    public function i_swipe_in_the_app(string $direction) {
        $method = 'swipe' . ucwords($direction);

        $this->evaluate_script("window.behat.getAngularInstance('ion-content', 'CoreSwipeNavigationDirective').$method()");

        $this->wait_for_pending_js();

        // Wait swipe animation to finish.
        $this->getSession()->wait(300);
    }

    /**
     * Check if elements are selected in the app.
     *
     * @Then /^(".+") should( not)? be selected in the app$/
     * @param string $locator
     * @param bool $not
     */
    public function be_selected_in_the_app(string $locator, bool $not = false) {
        $locator = $this->parse_element_locator($locator);

        $this->spin(function() use ($locator, $not) {
            $result = $this->evaluate_script("return window.behat.isSelected($locator);");

            switch ($result) {
                case 'YES':
                    if ($not) {
                        throw new ExpectationException("Item was selected and shouldn't have", $this->getSession()->getDriver());
                    }
                    break;
                case 'NO':
                    if (!$not) {
                        throw new ExpectationException("Item wasn't selected and should have", $this->getSession()->getDriver());
                    }
                    break;
                default:
                    throw new DriverException('Error finding item - ' . $result);
            }

            return true;
        });

        $this->wait_for_pending_js();
    }

    /**
     * Carries out the login steps for the app, assuming the user is on the app login page. Called
     * from behat_auth.php.
     *
     * @param string $username Username (and password)
     * @throws Exception Any error
     */
    public function login(string $username) {
        $this->i_set_the_field_in_the_app('Username', $username);
        $this->i_set_the_field_in_the_app('Password', $username);

        // Note there are two 'Log in' texts visible (the title and the button) so we have to use
        // a 'near' value here.
        $this->i_press_in_the_app('"Log in" near "Forgotten"');

        // Wait until the main page appears.
        $this->spin(
                function($context, $args) {
                    $mainmenu = $context->getSession()->getPage()->find('xpath', '//page-core-mainmenu');
                    if ($mainmenu) {
                        return true;
                    }
                    throw new DriverException('Moodle App main page not loaded after login');
                }, false, 30);

        // Wait for JS to finish as well.
        $this->wait_for_pending_js();
    }

    /**
     * Enter site.
     */
    protected function enter_site() {
        if (!$this->is_in_login_page()) {
            // Already in the site.
            return;
        }

        global $CFG;

        $this->i_set_the_field_in_the_app('Your site', $CFG->wwwroot);
        $this->i_press_in_the_app('"Connect to your site"');
        $this->wait_for_pending_js();
    }

    /**
     * Shortcut to  let the user enter a course in the app.
     *
     * @Given I entered the course :coursename as :username in the app
     * @Given I entered the course :coursename in the app
     * @param string $coursename Course name
     * @throws DriverException If the button push doesn't work
     */
    public function i_entered_the_course_in_the_app(string $coursename, ?string $username = null) {
        $courseid = $this->get_course_id($coursename);
        if (!$courseid) {
            throw new DriverException("Course '$coursename' not found");
        }

        if ($username) {
            $this->i_launch_the_app();

            $this->open_moodleapp_custom_login_url($username, "/course/view.php?id=$courseid", '//page-core-course-index');
        } else {
            $this->open_moodleapp_custom_url("/course/view.php?id=$courseid", '//page-core-course-index');
        }
    }

    /**
     * User enters a course in the app.
     *
     * @Given I enter the course :coursename in the app
     * @param string $coursename Course name
     * @throws DriverException If the button push doesn't work
     */
    public function i_enter_the_course_in_the_app(string $coursename, ?string $username = null) {
        if (!is_null($username)) {
            $this->i_enter_the_app();
            $this->login($username);
        }

        $mycoursesfound = $this->evaluate_script("return window.behat.find({ text: 'My courses', selector: 'ion-tab-button'});");

        if ($mycoursesfound !== 'OK') {
            // My courses not present enter from Dashboard.
            $this->i_press_in_the_app('"Home" "ion-tab-button"');
            $this->i_press_in_the_app('"Dashboard"');
            $this->i_press_in_the_app('"'.$coursename.'" near "Course overview"');

            $this->wait_for_pending_js();

            return;
        }

        $this->i_press_in_the_app('"My courses" "ion-tab-button"');
        $this->i_press_in_the_app('"'.$coursename.'"');

        $this->wait_for_pending_js();
    }

    /**
     * User enters an activity in a course in the app.
     *
     * @Given I entered the :activity activity :activityname on course :course as :username in the app
     * @Given I entered the :activity activity :activityname on course :course in the app
     * @throws DriverException If the button push doesn't work
     */
    public function i_enter_the_activity_in_the_app(string $activity, string $activityname, string $coursename, ?string $username = null) {
        $cm = $this->get_cm_by_activity_name_and_course($activity, $activityname, $coursename);
        if (!$cm) {
            throw new DriverException("'$activityname' activity '$activityname' not found");
        }

        // Visit course first.
        $this->i_entered_the_course_in_the_app($coursename, $username);

        $pageurl = "/mod/$activity/view.php?id={$cm->id}";
        $this->open_moodleapp_custom_url($pageurl);
    }

    /**
     * Presses standard buttons in the app.
     *
     * @When /^I press the (back|more menu|page menu|user menu|main menu) button in the app$/
     * @param string $button Button type
     * @throws DriverException If the button push doesn't work
     */
    public function i_press_the_standard_button_in_the_app(string $button) {
        $this->spin(function() use ($button) {
            $result = $this->evaluate_script("return window.behat.pressStandard('$button');");

            if ($result !== 'OK') {
                throw new DriverException('Error pressing standard button - ' . $result);
            }

            return true;
        });

        $this->wait_for_pending_js();
    }

    /**
     * Receives push notifications.
     *
     * @When /^I receive a push notification in the app for:$/
     * @param TableNode $data
     */
    public function i_receive_a_push_notification(TableNode $data) {
        global $DB, $CFG;

        $data = (object) $data->getColumnsHash()[0];
        $module = $DB->get_record('course_modules', ['idnumber' => $data->module]);
        $discussion = $DB->get_record('forum_discussions', ['name' => $data->discussion]);
        $notification = json_encode([
            'site' => md5($CFG->behat_wwwroot . $data->username),
            'courseid' => $discussion->course,
            'moodlecomponent' => 'mod_forum',
            'name' => 'posts',
            'contexturl' => '',
            'notif' => 1,
            'customdata' => [
                'discussionid' => $discussion->id,
                'cmid' => $module->id,
                'instance' => $discussion->forum,
            ],
        ]);

        $this->evaluate_script("return window.pushNotifications.notificationClicked($notification)");
        $this->wait_for_pending_js();
    }

    /**
     * Replace arguments from the content in the given activity field.
     *
     * @Given /^I replace the arguments in "([^"]+)" "([^"]+)"$/
     */
    public function i_replace_arguments_in_the_activity(string $idnumber, string $field) {
        global $DB;

        $coursemodule = $DB->get_record('course_modules', compact('idnumber'));
        $module = $DB->get_record('modules', ['id' => $coursemodule->module]);
        $activity = $DB->get_record($module->name, ['id' => $coursemodule->instance]);

        $DB->update_record($module->name, [
            'id' => $coursemodule->instance,
            $field => $this->replace_arguments($activity->{$field}),
        ]);
    }

    /**
     * Opens a custom link.
     *
     * @Given /^I open a custom link in the app for:$/
     * @param TableNode $data
     */
    public function i_open_a_custom_link(TableNode $data) {
        global $DB;

        $data = $data->getColumnsHash()[0];
        $title = array_keys($data)[0];
        $data = (object) $data;

        switch ($title) {
            case 'discussion':
                $discussion = $DB->get_record('forum_discussions', ['name' => $data->discussion]);
                $pageurl = "/mod/forum/discuss.php?d={$discussion->id}";

                break;

            case 'assign':
            case 'bigbluebuttonbn':
            case 'book':
            case 'chat':
            case 'choice':
            case 'data':
            case 'feedback':
            case 'folder':
            case 'forum':
            case 'glossary':
            case 'h5pactivity':
            case 'imscp':
            case 'label':
            case 'lesson':
            case 'lti':
            case 'page':
            case 'quiz':
            case 'resource':
            case 'scorm':
            case 'survey':
            case 'url':
            case 'wiki':
            case 'workshop':
                $name = $data->$title;
                $module = $DB->get_record($title, ['name' => $name]);
                $cm = get_coursemodule_from_instance($title, $module->id);
                $pageurl = "/mod/$title/view.php?id={$cm->id}";
                break;

            default:
                throw new DriverException('Invalid custom link title - ' . $title);
        }

        $this->open_moodleapp_custom_url($pageurl);
    }

    /**
     * Closes a popup by clicking on the 'backdrop' behind it.
     *
     * @When I close the popup in the app
     * @throws DriverException If there isn't a popup to close
     */
    public function i_close_the_popup_in_the_app() {
        $this->spin(function()  {
            $result = $this->evaluate_script("return window.behat.closePopup();");

            if ($result !== 'OK') {
                throw new DriverException('Error closing popup - ' . $result);
            }

            return true;
        });

        $this->wait_for_pending_js();
    }

    /**
     * Override app config.
     *
     * @Given /^the app has the following config:$/
     * @param TableNode $data
     */
    public function the_app_has_the_following_config(TableNode $data) {
        foreach ($data->getRows() as $configrow) {
            $this->appconfig[$configrow[0]] = json_decode($configrow[1]);
        }
    }

    /**
     * Clicks on / touches something that is visible in the app.
     *
     * Note it is difficult to use the standard 'click on' or 'press' steps because those do not
     * distinguish visible items and the app always has many non-visible items in the DOM.
     *
     * @Then /^I press (".+") in the app$/
     * @param string $locator Element locator
     * @throws DriverException If the press doesn't work
     */
    public function i_press_in_the_app(string $locator) {
        $locator = $this->parse_element_locator($locator);

        $this->spin(function() use ($locator) {
            $result = $this->evaluate_script("return window.behat.press($locator);");

            if ($result !== 'OK') {
                throw new DriverException('Error pressing item - ' . $result);
            }

            return true;
        });

        $this->wait_for_pending_js();
    }

    /**
     * Select an item from a list of options, such as a radio button.
     *
     * It may be necessary to use this step instead of "I press..." because radio buttons in Ionic are initialized
     * with JavaScript, and clicks may not work until they are initialized properly which may cause flaky tests due
     * to race conditions.
     *
     * @Then /^I (unselect|select) (".+") in the app$/
     * @param string $selectedtext
     * @param string $locator
     * @throws DriverException If the press doesn't work
     */
    public function i_select_in_the_app(string $selectedtext, string $locator) {
        $selected = $selectedtext === 'select' ? 'YES' : 'NO';
        $locator = $this->parse_element_locator($locator);

        $this->spin(function() use ($selectedtext, $selected, $locator) {
            // Don't do anything if the item is already in the expected state.
            $result = $this->evaluate_script("return window.behat.isSelected($locator);");

            if ($result === $selected) {
                return true;
            }

            // Press item.
            $result = $this->evaluate_script("return window.behat.press($locator);");

            if ($result !== 'OK') {
                throw new DriverException('Error pressing item - ' . $result);
            }

            // Check that it worked as expected.
            $this->wait_for_pending_js();

            $result = $this->evaluate_script("return window.behat.isSelected($locator);");

            switch ($result) {
                case 'YES':
                case 'NO':
                    if ($result !== $selected) {
                        throw new ExpectationException("Item wasn't $selectedtext after pressing it", $this->getSession()->getDriver());
                    }

                    return true;
                default:
                    throw new DriverException('Error finding item - ' . $result);
            }
        });

        $this->wait_for_pending_js();
    }

    /**
     * Sets a field to the given text value in the app.
     *
     * Currently this only works for input fields which must be identified using a partial or
     * exact match on the placeholder text.
     *
     * @Given /^I set the field "((?:[^"]|\\")+)" to "((?:[^"]|\\")*)" in the app$/
     * @param string $field Text identifying field
     * @param string $value Value for field
     * @throws DriverException If the field set doesn't work
     */
    public function i_set_the_field_in_the_app(string $field, string $value) {
        $field = addslashes_js($field);
        $value = addslashes_js($value);

        $this->spin(function() use ($field, $value) {
            $result = $this->evaluate_script("return window.behat.setField(\"$field\", \"$value\");");

            if ($result !== 'OK') {
                throw new DriverException('Error setting field - ' . $result);
            }

            return true;
        });

        $this->wait_for_pending_js();
    }

    /**
     * Checks that the current header stripe in the app contains the expected text.
     *
     * This can be used to see if the app went to the expected page.
     *
     * @Then /^the header should be "((?:[^"]|\\")+)" in the app$/
     * @param string $text Expected header text
     * @throws DriverException If the header can't be retrieved
     * @throws ExpectationException If the header text is different to the expected value
     */
    public function the_header_should_be_in_the_app(string $text) {
        $this->spin(function() use ($text) {
            $result = $this->evaluate_script('return window.behat.getHeader();');

            if (substr($result, 0, 3) !== 'OK:') {
                throw new DriverException('Error getting header - ' . $result);
            }

            $header = substr($result, 3);
            if (trim($header) !== trim($text)) {
                throw new ExpectationException(
                    "The header text was not as expected: '$header'",
                    $this->getSession()->getDriver()
                );
            }

            return true;
        });
    }

    /**
     * Check that the app opened a new browser tab.
     *
     * @Then /^the app should( not)? have opened a browser tab(?: with url "(?P<pattern>[^"]+)")?$/
     * @param bool $not
     * @param string $urlpattern
     */
    public function the_app_should_have_opened_a_browser_tab(bool $not = false, ?string $urlpattern = null) {
        $this->spin(function() use ($not, $urlpattern) {
            $windowNames = $this->getSession()->getWindowNames();
            $openedbrowsertab = count($windowNames) === 2;

            if ((!$not && !$openedbrowsertab) || ($not && $openedbrowsertab && is_null($urlpattern))) {
                throw new ExpectationException(
                    $not
                        ? 'Did not expect the app to have opened a browser tab'
                        : 'Expected the app to have opened a browser tab',
                    $this->getSession()->getDriver()
                );
            }

            if (!is_null($urlpattern)) {
                $this->getSession()->switchToWindow($windowNames[1]);
                $windowurl = $this->getSession()->getCurrentUrl();
                $windowhaspattern = preg_match("/$urlpattern/", $windowurl);
                $this->getSession()->switchToWindow($windowNames[0]);

                if ($not === $windowhaspattern) {
                    throw new ExpectationException(
                        $not
                            ? "Did not expect the app to have opened a browser tab with pattern '$urlpattern'"
                            : "Browser tab url does not match pattern '$urlpattern', it is '$windowurl'",
                        $this->getSession()->getDriver()
                    );
                }
            }

            return true;
        });
    }

    /**
     * Switches to a newly-opened browser tab.
     *
     * This assumes the app opened a new tab.
     *
     * @Given I switch to the browser tab opened by the app
     * @throws DriverException If there aren't exactly 2 tabs open
     */
    public function i_switch_to_the_browser_tab_opened_by_the_app() {
        $windowNames = $this->getSession()->getWindowNames();
        if (count($windowNames) !== 2) {
            throw new DriverException('Expected to see 2 tabs open, not ' . count($windowNames));
        }
        $this->getSession()->switchToWindow($windowNames[1]);
    }

    /**
     * Force cron tasks instead of waiting for the next scheduled execution.
     *
     * @When I run cron tasks in the app
     */
    public function i_run_cron_tasks_in_the_app() {
        $session = $this->getSession();

        // Force cron tasks execution and wait until they are completed.
        $operationid = random_string();

        $session->executeScript(
            "cronProvider.forceSyncExecution().then(() => { window['behat_{$operationid}_completed'] = true; });"
        );
        $this->spin(
            function() use ($session, $operationid) {
                return $session->evaluateScript("window['behat_{$operationid}_completed'] || false");
            },
            false,
            60,
            new ExpectationException('Forced cron tasks in the app took too long to complete', $session)
        );

        // Trigger Angular change detection.
        $this->trigger_angular_change_detection();
    }

    /**
     * Wait until loading has finished.
     *
     * @When I wait loading to finish in the app
     */
    public function i_wait_loading_to_finish_in_the_app() {
        $session = $this->getSession();

        $this->spin(
            function() use ($session) {
                $this->trigger_angular_change_detection();

                $nodes = $this->find_all('css', 'core-loading ion-spinner');

                foreach ($nodes as $node) {
                    if (!$node->isVisible()) {
                        continue;
                    }

                    return false;
                }

                return true;
            },
            false,
            60,
            new ExpectationException('"Loading took too long to complete', $session)
        );
    }

    /**
     * Closes the current browser tab.
     *
     * This assumes it was opened by the app and you will now get back to the app.
     *
     * @Given I close the browser tab opened by the app
     * @throws DriverException If there aren't exactly 2 tabs open
     */
    public function i_close_the_browser_tab_opened_by_the_app() {
        $names = $this->getSession()->getWindowNames();
        if (count($names) !== 2) {
            throw new DriverException('Expected to see 2 tabs open, not ' . count($names));
        }
        // Make sure the browser tab is selected.
        if ($this->getSession()->getWindowName() !== $names[1]) {
            $this->getSession()->switchToWindow($names[1]);
        }

        $this->execute_script('window.close();');
        $this->getSession()->switchToWindow($names[0]);
    }

    /**
     * Switch navigator online mode.
     *
     * @Given /^I switch offline mode to "(true|false)"$/
     * @param string $offline New value for navigator online mode
     * @throws DriverException If the navigator.online mode is not available
     */
    public function i_switch_offline_mode(string $offline) {
        $this->execute_script("appProvider.setForceOffline($offline);");
    }

}
