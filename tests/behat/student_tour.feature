@mod @mod_assign @assignsubmission @assignsubmission_vrtour
Feature: Students make a virtual tour as an assignment submission
  In order to have students build virtual tours of 360° photos
  As a teacher
  I need to enable Virtual tour on an assignment, and students need to open the tour editor from their submission

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name            | assignsubmission_onlinetext_enabled | assignsubmission_file_enabled |
      | assign   | C1     | Tour assignment | 1                                   | 0                             |

  Scenario: A teacher enables Virtual tour, the student opens the tour editor and the teacher sees the summary
    Given I am on the "Tour assignment" Activity page logged in as teacher1
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "assignsubmission_vrtour_enabled" to "1"
    And I set the field "assignsubmission_vrtour_minpoints" to "2"
    And I press "Save and display"
    And I log out
    When I am on the "Tour assignment" Activity page logged in as student1
    And I press "Add submission"
    Then I should see "No tour yet."
    And I should see "This assignment asks for at least 2 points of interest, on up to 10 photos."
    And I should see "Save what you have written in the other parts of this form before you open it."
    And I click on "Build your tour" "link"
    And "[data-region='vrroom-editor']" "css_element" should exist
    And I should see "Back to my submission"
    And I should see "No 360° camera?"
    And I log out
    And I am on the "Tour assignment" Activity page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    And I should see "Virtual tour"
    And I should see "No tour yet."
