<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */


namespace api\v4\Action;

use api\v4\Api4TestBase;
use Civi\Api4\ACLEntityRole;
use Civi\Api4\Activity;
use Civi\Api4\Address;
use Civi\Api4\Campaign;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\CustomGroup;
use Civi\Api4\Email;
use Civi\Api4\EntityTag;
use Civi\Api4\UserJob;
use Civi\Api4\Utils\CoreUtil;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 */
class GetFieldsTest extends Api4TestBase implements TransactionalInterface {

  public function setUp(): void {
    \CRM_Core_BAO_ConfigSetting::enableAllComponents();
    parent::setUp();
  }

  public function testOptionsAreReturned(): void {
    $fields = Contact::getFields(FALSE)
      ->execute()
      ->indexBy('name');
    $this->assertTrue($fields['gender_id']['options']);
    $this->assertFalse($fields['first_name']['options']);

    $fields = Contact::getFields(FALSE)
      ->setLoadOptions(TRUE)
      ->execute()
      ->indexBy('name');
    $this->assertTrue(is_array($fields['gender_id']['options']));
    $this->assertFalse($fields['first_name']['options']);
  }

  public function testContactGetFields(): void {
    $fields = Contact::getFields(FALSE)
      ->execute()
      ->indexBy('name');
    // Ensure table & column are returned
    $this->assertEquals('civicrm_contact', $fields['display_name']['table_name']);
    $this->assertEquals('display_name', $fields['display_name']['column_name']);

    // Check suffixes
    $this->assertEquals(['name', 'label', 'icon'], $fields['contact_type']['suffixes']);
    $this->assertEquals(['name', 'label', 'icon'], $fields['contact_sub_type']['suffixes']);

    // Check `required` and `nullable`
    $this->assertFalse($fields['is_deleted']['required']);
    $this->assertFalse($fields['is_deleted']['nullable']);
    $this->assertFalse($fields['id']['nullable']);
    $this->assertFalse($fields['id']['required']);
    $this->assertNull($fields['id']['default_value']);
  }

  public function testComponentFields(): void {
    \CRM_Core_BAO_ConfigSetting::disableComponent('CiviCampaign');
    $fields = \Civi\Api4\Event::getFields()
      ->addWhere('name', 'CONTAINS', 'campaign')
      ->execute();
    $this->assertCount(0, $fields);
    \CRM_Core_BAO_ConfigSetting::enableComponent('CiviCampaign');
    $fields = \Civi\Api4\Event::getFields()
      ->addWhere('name', 'CONTAINS', 'campaign')
      ->execute();
    $this->assertCount(1, $fields);
  }

  public function testEmailFields(): void {
    $getFields = Email::getFields(FALSE)
      ->setAction('get')
      ->execute()->indexBy('name');

    $this->assertEquals('Text', $getFields['email']['input_type']);

    $createFields = Email::getFields(FALSE)
      ->setAction('create')
      ->execute()->indexBy('name');

    $this->assertEquals('Email', $createFields['email']['input_type']);
    $this->assertIsInt($createFields['location_type_id']['default_value']);

    // Check `required` and `nullable`
    $this->assertFalse($createFields['is_primary']['required']);
    $this->assertFalse($createFields['is_primary']['nullable']);
    $this->assertFalse($createFields['is_primary']['default_value']);
    $this->assertFalse($createFields['id']['required']);
    $this->assertFalse($createFields['id']['nullable']);
    $this->assertNull($createFields['id']['default_value']);
  }

  public function testInternalPropsAreHidden(): void {
    // Public getFields should not contain @internal props
    $fields = Contact::getFields(FALSE)
      ->execute();
    foreach ($fields as $field) {
      $this->assertArrayNotHasKey('output_formatters', $field);
    }
    // Internal entityFields should contain @internal props
    $fields = Contact::get(FALSE)
      ->entityFields();
    foreach ($fields as $field) {
      $this->assertArrayHasKey('output_formatters', $field);
    }
  }

  public function testPrefetchDisabled(): void {
    \CRM_Core_BAO_ConfigSetting::enableComponent('CiviContribute');
    \CRM_Core_BAO_ConfigSetting::enableComponent('CiviCampaign');
    Campaign::create()->setValues(['name' => 'Big Campaign', 'title' => 'Biggie'])->execute();
    // The campaign_id field has prefetch = disabled in the schema,
    // Which means the options will NOT load but suffixes are still available
    $fields = Contribution::getFields(FALSE)
      ->setLoadOptions(['name', 'label'])
      ->execute()->indexBy('name');
    $this->assertFalse($fields['campaign_id']['options']);
    $this->assertEquals(['name', 'label'], $fields['campaign_id']['suffixes']);
  }

  public function testRequiredAndNullableAndDeprecated(): void {
    $actFields = Activity::getFields(FALSE)
      ->setAction('create')
      ->execute()->indexBy('name');

    $this->assertFalse($actFields['id']['required']);
    $this->assertFalse($actFields['id']['nullable']);
    $this->assertTrue($actFields['activity_type_id']['required']);
    $this->assertFalse($actFields['activity_type_id']['nullable']);
    $this->assertNull($actFields['activity_type_id']['default_value']);
    $this->assertFalse($actFields['is_deleted']['required']);
    $this->assertFalse($actFields['is_deleted']['nullable']);
    $this->assertFalse($actFields['is_deleted']['default_value']);
    $this->assertFalse($actFields['subject']['required']);
    $this->assertTrue($actFields['subject']['nullable']);
    $this->assertFalse($actFields['subject']['deprecated']);
    $this->assertTrue($actFields['phone_id']['deprecated']);

    $getFields = Activity::getFields(FALSE)
      ->setAction('get')
      ->execute()->indexBy('name');

    $this->assertFalse($getFields['is_deleted']['required']);
    $this->assertFalse($getFields['is_deleted']['nullable']);
    $this->assertFalse($getFields['is_deleted']['default_value']);

    $aclFields = ACLEntityRole::getFields(FALSE)
      ->setAction('create')
      ->execute()->indexBy('name');
    $this->assertTrue($aclFields['is_active']['default_value']);
    $this->assertFalse($aclFields['is_active']['nullable']);
    $this->assertFalse($aclFields['is_active']['required']);
  }

  public function testGetSuffixes(): void {
    $actFields = Activity::getFields(FALSE)
      ->execute()->indexBy('name');
    $this->assertEquals(['name', 'label', 'description'], $actFields['engagement_level']['suffixes']);
    $this->assertEquals(['name', 'label', 'description', 'icon'], $actFields['activity_type_id']['suffixes']);
    $this->assertEquals(['name', 'label', 'description', 'color'], $actFields['status_id']['suffixes']);
    $this->assertEquals(['name', 'label', 'description', 'color'], $actFields['tags']['suffixes']);

    $addressFields = Address::getFields(FALSE)
      ->execute()->indexBy('name');
    $this->assertEquals(['label', 'abbr'], $addressFields['country_id']['suffixes']);
    $this->assertEquals(['label', 'abbr'], $addressFields['county_id']['suffixes']);
    $this->assertEquals(['label', 'abbr'], $addressFields['state_province_id']['suffixes']);

    $customGroupFields = CustomGroup::getFields(FALSE)
      ->execute()->indexBy('name');
    $this->assertEquals(['name', 'label', 'grouping'], $customGroupFields['extends']['suffixes']);
    $this->assertEquals(['name', 'label', 'grouping'], $customGroupFields['extends_entity_column_id']['suffixes']);

    $userJobFields = UserJob::getFields(FALSE)
      ->execute()->indexBy('name');
    $this->assertEquals(['name', 'label', 'url'], $userJobFields['job_type']['suffixes']);
  }

  public function testDynamicFks(): void {
    $tagFields = EntityTag::getFields(FALSE)
      ->execute()->indexBy('name');
    $this->assertEmpty($tagFields['entity_id']['fk_entity']);

    $tagFields = EntityTag::getFields(FALSE)
      ->addValue('entity_table', 'civicrm_activity')
      ->execute()->indexBy('name');
    $this->assertEquals('Activity', $tagFields['entity_id']['fk_entity']);

    $tagFields = EntityTag::getFields(FALSE)
      ->addValue('entity_table:name', 'Contact')
      ->execute()->indexBy('name');
    $this->assertEquals('Contact', $tagFields['entity_id']['fk_entity']);
  }

  public function testFiltersAreReturned(): void {
    $field = Contact::getFields(FALSE)
      ->addWhere('name', '=', 'employer_id')
      ->execute()->single();
    $this->assertEquals(['contact_type' => 'Organization'], $field['input_attrs']['filter']);
  }

  public function testTopSortFields(): void {
    $sampleFields = [
      [
        'name' => 'd',
        'title' => 'Fourth',
        'input_attrs' => [
          'control_field' => 'a',
        ],
      ],
      [
        'name' => 'a',
        'title' => 'Third',
        'input_attrs' => [
          'control_field' => 'c',
        ],
      ],
      [
        'name' => 'b',
        'title' => 'First',
      ],
      [
        'name' => 'c',
        'title' => 'Second',
        'input_attrs' => [
          'control_field' => 'b',
        ],
      ],
    ];
    CoreUtil::topSortFields($sampleFields);
    $this->assertEquals(['First', 'Second', 'Third', 'Fourth'], array_column($sampleFields, 'title'));
  }

  public function entityFieldsWithDependencies(): array {
    return [
      ['Contact', ['contact_type', 'contact_sub_type']],
      ['Case', ['case_type_id', 'status_id']],
      ['EntityTag', ['entity_table', 'tag_id']],
      ['Address', ['country_id', 'state_province_id', 'county_id']],
      ['ActionSchedule', ['mapping_id', 'entity_value', 'entity_status']],
      ['CustomGroup', ['extends', 'extends_entity_column_id', 'extends_entity_column_value']],
    ];
  }

  /**
   * @dataProvider entityFieldsWithDependencies
   */
  public function testTopSortEntityFields(string $entityName, array $orderedFieldNames): void {
    $entityFields = (array) civicrm_api4($entityName, 'getFields', [
      'checkPermissions' => FALSE,
      'where' => [['name', 'IN', $orderedFieldNames]],
    ]);
    // Try sorting with different starting orders; the outcome should always be the same
    $entityFields2 = array_reverse($entityFields);
    CoreUtil::topSortFields($entityFields);
    CoreUtil::topSortFields($entityFields2);
    $this->assertEquals($orderedFieldNames, array_column($entityFields, 'name'));
    $this->assertEquals($orderedFieldNames, array_column($entityFields2, 'name'));
  }

}
