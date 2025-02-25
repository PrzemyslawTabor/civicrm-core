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

/**
 * Page for displaying Campaigns.
 */
class CRM_Campaign_Page_DashBoard extends CRM_Core_Page {

  /**
   * The action links that we need to display for the browse screen.
   *
   * @var array
   */
  private static $_campaignActionLinks;
  private static $_surveyActionLinks;
  private static $_petitionActionLinks;

  public $_tabs;

  /**
   * Get the action links for this page.
   *
   * @return array
   */
  public static function campaignActionLinks() {
    // check if variable _actionsLinks is populated
    if (!isset(self::$_campaignActionLinks)) {
      self::$_campaignActionLinks = [
        CRM_Core_Action::UPDATE => [
          'name' => ts('Edit'),
          'url' => 'civicrm/campaign/add',
          'qs' => 'reset=1&action=update&id=%%id%%',
          'title' => ts('Update Campaign'),
        ],
        CRM_Core_Action::DISABLE => [
          'name' => ts('Disable'),
          'title' => ts('Disable Campaign'),
          'ref' => 'crm-enable-disable',
        ],
        CRM_Core_Action::ENABLE => [
          'name' => ts('Enable'),
          'title' => ts('Enable Campaign'),
          'ref' => 'crm-enable-disable',
        ],
        CRM_Core_Action::DELETE => [
          'name' => ts('Delete'),
          'url' => 'civicrm/campaign/add',
          'qs' => 'action=delete&reset=1&id=%%id%%',
          'title' => ts('Delete Campaign'),
        ],
      ];
    }

    return self::$_campaignActionLinks;
  }

  /**
   * @return array
   */
  public static function surveyActionLinks() {
    // check if variable _actionsLinks is populated
    if (!isset(self::$_surveyActionLinks)) {
      self::$_surveyActionLinks = [
        CRM_Core_Action::UPDATE => [
          'name' => ts('Edit'),
          'url' => 'civicrm/survey/configure/main',
          'qs' => 'action=update&id=%%id%%&reset=1',
          'title' => ts('Update Survey'),
        ],
        CRM_Core_Action::DISABLE => [
          'name' => ts('Disable'),
          'ref' => 'crm-enable-disable',
          'title' => ts('Disable Survey'),
        ],
        CRM_Core_Action::ENABLE => [
          'name' => ts('Enable'),
          'ref' => 'crm-enable-disable',
          'title' => ts('Enable Survey'),
        ],
        CRM_Core_Action::DELETE => [
          'name' => ts('Delete'),
          'url' => 'civicrm/survey/delete',
          'qs' => 'id=%%id%%&reset=1',
          'title' => ts('Delete Survey'),
        ],
      ];
    }

    return self::$_surveyActionLinks;
  }

  /**
   * @return array
   */
  public static function petitionActionLinks() {
    if (!isset(self::$_petitionActionLinks)) {
      self::$_petitionActionLinks = self::surveyActionLinks();
      self::$_petitionActionLinks[CRM_Core_Action::UPDATE] = [
        'name' => ts('Edit'),
        'url' => 'civicrm/petition/add',
        'qs' => 'action=update&id=%%id%%&reset=1',
        'title' => ts('Update Petition'),
      ];
      self::$_petitionActionLinks[CRM_Core_Action::DISABLE] = [
        'name' => ts('Disable'),
        'ref' => 'crm-enable-disable',
        'title' => ts('Disable Petition'),
      ];
      self::$_petitionActionLinks[CRM_Core_Action::ENABLE] = [
        'name' => ts('Enable'),
        'ref' => 'crm-enable-disable',
        'title' => ts('Enable Petition'),
      ];
      self::$_petitionActionLinks[CRM_Core_Action::DELETE] = [
        'name' => ts('Delete'),
        'url' => 'civicrm/petition/add',
        'qs' => 'action=delete&id=%%id%%&reset=1',
        'title' => ts('Delete Petition'),
      ];
      self::$_petitionActionLinks[CRM_Core_Action::PROFILE] = [
        'name' => ts('Sign'),
        'url' => 'civicrm/petition/sign',
        'qs' => 'sid=%%id%%&reset=1',
        'title' => ts('Sign Petition'),
        'fe' => TRUE,
        //CRM_Core_Action::PROFILE is used because there isn't a specific action for sign
      ];
      self::$_petitionActionLinks[CRM_Core_Action::BROWSE] = [
        'name' => ts('Signatures'),
        'url' => 'civicrm/activity/search',
        'qs' => 'survey=%%id%%&force=1',
        'title' => ts('List the signatures'),
        //CRM_Core_Action::PROFILE is used because there isn't a specific action for sign
      ];
    }

    return self::$_petitionActionLinks;
  }

  /**
   * @return mixed
   */
  public function browseCampaign() {
    // ensure valid javascript (these must have a value set)
    $this->assign('searchParams', json_encode(NULL));
    $this->assign('campaignTypes', json_encode(NULL));
    $this->assign('campaignStatus', json_encode(NULL));

    $this->assign('addCampaignUrl', CRM_Utils_System::url('civicrm/campaign/add', 'reset=1&action=add'));
    $campaignCount = CRM_Campaign_BAO_Campaign::getCampaignCount();
    //don't load find interface when no campaigns in db.
    if (!$campaignCount) {
      $this->assign('hasCampaigns', FALSE);
      return;
    }
    $this->assign('hasCampaigns', TRUE);

    //build the ajaxify campaign search and selector.
    $controller = new CRM_Core_Controller_Simple('CRM_Campaign_Form_Search_Campaign', ts('Search Campaigns'));
    $controller->set('searchTab', 'campaign');
    $controller->setEmbedded(TRUE);
    $controller->process();
    return $controller->run();
  }

  /**
   * @param array $params
   *
   * @return array
   */
  public static function getCampaignSummary($params = []) {
    $campaignsData = [];

    //get the campaigns.
    $campaigns = CRM_Campaign_BAO_Campaign::getCampaignSummary($params);
    if (!empty($campaigns)) {
      $config = CRM_Core_Config::singleton();
      $campaignType = CRM_Campaign_PseudoConstant::campaignType();
      $campaignStatus = CRM_Campaign_PseudoConstant::campaignStatus();
      $properties = [
        'id',
        'name',
        'title',
        'status_id',
        'description',
        'campaign_type_id',
        'is_active',
        'start_date',
        'end_date',
      ];
      foreach ($campaigns as $cmpid => $campaign) {
        foreach ($properties as $prop) {
          $campaignsData[$cmpid][$prop] = $campaign[$prop] ?? NULL;
        }
        $statusId = $campaign['status_id'] ?? NULL;
        $campaignsData[$cmpid]['status'] = $campaignStatus[$statusId] ?? NULL;
        $campaignsData[$cmpid]['campaign_id'] = $campaign['id'];
        $campaignsData[$cmpid]['campaign_type'] = $campaignType[$campaign['campaign_type_id']];

        $action = array_sum(array_keys(self::campaignActionLinks()));
        if ($campaign['is_active']) {
          $action -= CRM_Core_Action::ENABLE;
        }
        else {
          $action -= CRM_Core_Action::DISABLE;
        }

        $isActive = ts('No');
        if ($campaignsData[$cmpid]['is_active']) {
          $isActive = ts('Yes');
        }
        $campaignsData[$cmpid]['isActive'] = $isActive;

        if (!empty($campaignsData[$cmpid]['start_date'])) {
          $campaignsData[$cmpid]['start_date'] = CRM_Utils_Date::customFormat($campaignsData[$cmpid]['start_date'],
            $config->dateformatFull
          );
        }
        if (!empty($campaignsData[$cmpid]['end_date'])) {
          $campaignsData[$cmpid]['end_date'] = CRM_Utils_Date::customFormat($campaignsData[$cmpid]['end_date'],
            $config->dateformatFull
          );
        }
        $campaignsData[$cmpid]['action'] = CRM_Core_Action::formLink(self::campaignActionLinks(),
          $action,
          ['id' => $campaign['id']],
          ts('more'),
          FALSE,
          'campaign.dashboard.row',
          'Campaign',
          $campaign['id']
        );
      }
    }

    return $campaignsData;
  }

  /**
   * @return mixed
   */
  public function browseSurvey() {
    // ensure valid javascript - this must have a value set
    $this->assign('searchParams', json_encode(NULL));
    $this->assign('surveyTypes', json_encode(NULL));
    $this->assign('surveyCampaigns', json_encode(NULL));

    $this->assign('addSurveyUrl', CRM_Utils_System::url('civicrm/survey/add', 'reset=1&action=add'));

    $surveyCount = CRM_Campaign_BAO_Survey::getSurveyCount();
    //don't load find interface when no survey in db.
    if (!$surveyCount) {
      $this->assign('hasSurveys', FALSE);
      return;
    }
    $this->assign('hasSurveys', TRUE);

    //build the ajaxify survey search and selector.
    $controller = new CRM_Core_Controller_Simple('CRM_Campaign_Form_Search_Survey', ts('Search Survey'));
    $controller->set('searchTab', 'survey');
    $controller->setEmbedded(TRUE);
    $controller->process();
    return $controller->run();
  }

  /**
   * @param array $params
   *
   * @return array
   */
  public static function getSurveySummary($params = []) {
    $surveysData = [];

    //get the survey.
    $config = CRM_Core_Config::singleton();
    $surveys = CRM_Campaign_BAO_Survey::getSurveySummary($params);
    if (!empty($surveys)) {
      $campaigns = CRM_Campaign_BAO_Campaign::getCampaigns(NULL, NULL, FALSE, FALSE, FALSE, TRUE);
      $surveyType = CRM_Campaign_BAO_Survey::getSurveyActivityType();
      foreach ($surveys as $sid => $survey) {
        $surveysData[$sid] = $survey;
        $campaignId = $survey['campaign_id'] ?? NULL;
        $surveysData[$sid]['campaign'] = $campaigns[$campaignId] ?? NULL;
        $surveysData[$sid]['activity_type'] = $surveyType[$survey['activity_type_id']];
        if (!empty($survey['release_frequency'])) {
          $surveysData[$sid]['release_frequency'] = ts('1 Day', ['plural' => '%count Days', 'count' => $survey['release_frequency']]);
        }

        $action = array_sum(array_keys(self::surveyActionLinks($surveysData[$sid]['activity_type'])));
        if ($survey['is_active']) {
          $action -= CRM_Core_Action::ENABLE;
        }
        else {
          $action -= CRM_Core_Action::DISABLE;
        }

        $isActive = ts('No');
        if ($surveysData[$sid]['is_active']) {
          $isActive = ts('Yes');
        }
        $surveysData[$sid]['isActive'] = $isActive;

        // For some reason, 'is_default' is coming as a string.
        $surveysData[$sid]['is_default'] = boolval($surveysData[$sid]['is_default']);

        if ($surveysData[$sid]['result_id']) {
          $resultSet = '<a href= "javascript:displayResultSet( ' . $sid . ', ' . htmlspecialchars(json_encode($surveysData[$sid]['title'])) . ', ' . $surveysData[$sid]['result_id'] . ' )" title="' . ts('view result set', ['escape' => 'htmlattribute']) . '">' . ts('Result Set') . '</a>';
          $surveysData[$sid]['result_id'] = $resultSet;
        }
        else {
          $resultUrl = CRM_Utils_System::url("civicrm/survey/configure/results", "action=update&id={$sid}&reset=1");
          $surveysData[$sid]['result_id'] = "<a href='{$resultUrl}' class='status-warning'>(" . ts('Incomplete. Click to configure result set.') . ')</a>';
        }
        $surveysData[$sid]['action'] = CRM_Core_Action::formLink(self::surveyActionLinks($surveysData[$sid]['activity_type']),
          $action,
          ['id' => $sid],
          ts('more'),
          FALSE,
          'survey.dashboard.row',
          'Survey',
          $sid
        );

        if (($surveysData[$sid]['activity_type'] ?? NULL) != 'Petition') {
          $surveysData[$sid]['voterLinks'] = CRM_Campaign_BAO_Survey::buildPermissionLinks($sid,
            TRUE,
            ts('more')
          );
        }

        if ($reportID = CRM_Campaign_BAO_Survey::getReportID($sid)) {
          $url = CRM_Utils_System::url("civicrm/report/instance/{$reportID}", 'reset=1');
          $surveysData[$sid]['title'] = "<a href='{$url}' title='View Survey Report'>{$surveysData[$sid]['title']}</a>";
        }
      }
    }

    return $surveysData;
  }

  /**
   * Browse petitions.
   *
   * @return mixed|null
   */
  public function browsePetition() {
    // Ensure valid javascript - this must have a value set
    $this->assign('searchParams', json_encode(NULL));
    $this->assign('petitionCampaigns', json_encode(NULL));

    $this->assign('addPetitionUrl', CRM_Utils_System::url('civicrm/petition/add', 'reset=1&action=add'));

    $petitionCount = CRM_Campaign_BAO_Petition::getPetitionCount();
    //don't load find interface when no petition in db.
    if (!$petitionCount) {
      $this->assign('hasPetitions', FALSE);
      return NULL;
    }
    $this->assign('hasPetitions', TRUE);

    // Build the ajax petition search and selector.
    $controller = new CRM_Core_Controller_Simple('CRM_Campaign_Form_Search_Petition', ts('Search Petition'));
    $controller->set('searchTab', 'petition');
    $controller->setEmbedded(TRUE);
    $controller->process();
    return $controller->run();
  }

  /**
   * @param array $params
   *
   * @return array
   */
  public static function getPetitionSummary($params = []) {
    $config = CRM_Core_Config::singleton();
    $petitionsData = [];

    //get the petitions.
    $petitions = CRM_Campaign_BAO_Petition::getPetitionSummary($params);
    if (!empty($petitions)) {
      $campaigns = CRM_Campaign_BAO_Campaign::getCampaigns(NULL, NULL, FALSE, FALSE, FALSE, TRUE);
      $petitionType = CRM_Campaign_BAO_Survey::getSurveyActivityType('label', TRUE);
      foreach ($petitions as $pid => $petition) {
        $petitionsData[$pid] = $petition;
        $camapignId = $petition['campaign_id'] ?? NULL;
        $petitionsData[$pid]['campaign'] = $campaigns[$camapignId] ?? NULL;
        $petitionsData[$pid]['activity_type'] = $petitionType[$petition['activity_type_id']];

        $action = array_sum(array_keys(self::petitionActionLinks()));

        if ($petition['is_active']) {
          $action -= CRM_Core_Action::ENABLE;
        }
        else {
          $action -= CRM_Core_Action::DISABLE;
        }

        $isActive = ts('No');
        if ($petitionsData[$pid]['is_active']) {
          $isActive = ts('Yes');
        }
        $petitionsData[$pid]['isActive'] = $isActive;

        // For some reason, 'is_default' is coming as a string.
        $petitionsData[$pid]['is_default'] = boolval($petitionsData[$pid]['is_default']);

        $petitionsData[$pid]['action'] = CRM_Core_Action::formLink(self::petitionActionLinks(),
          $action,
          ['id' => $pid],
          ts('more'),
          FALSE,
          'petition.dashboard.row',
          'Petition',
          $pid
        );
      }
    }

    return $petitionsData;
  }

  public function browse() {
    $this->_tabs = [
      'campaign' => ts('Campaigns'),
      'survey' => ts('Surveys'),
      'petition' => ts('Petitions'),
    ];

    $subPageType = CRM_Utils_Request::retrieve('type', 'String', $this);
    // Load the data for a specific tab
    if ($subPageType) {
      if (!isset($this->_tabs[$subPageType])) {
        CRM_Utils_System::permissionDenied();
      }
      //load the data in tabs.
      $this->{'browse' . ucfirst($subPageType)}();
    }
    // Initialize tabs
    else {
      $this->buildTabs();
    }
    $this->assign('subPageType', ucfirst($subPageType));
  }

  /**
   * @return string
   */
  public function run() {
    if (!CRM_Campaign_BAO_Campaign::accessCampaign()) {
      CRM_Utils_System::permissionDenied();
    }

    $this->browse();

    return parent::run();
  }

  public function buildTabs() {
    $allTabs = [];
    foreach ($this->_tabs as $name => $title) {
      $allTabs[$name] = [
        'title' => $title,
        'valid' => TRUE,
        'active' => TRUE,
        'link' => CRM_Utils_System::url('civicrm/campaign', "reset=1&type=$name"),
        'extra' => NULL,
        'template' => NULL,
        'count' => NULL,
        'icon' => NULL,
        'class' => NULL,
      ];
    }
    $allTabs['campaign']['class'] = 'livePage';
    $this->assign('tabHeader', $allTabs);
    CRM_Core_Resources::singleton()
      ->addScriptFile('civicrm', 'templates/CRM/common/TabHeader.js', 1, 'html-header')
      ->addSetting([
        'tabSettings' => [
          // Tabs should use selectedChild, but Campaign has many legacy links
          'active' => strtolower($_GET['subPage'] ?? $_GET['selectedChild'] ?? 'campaign'),
        ],
      ]);
  }

}
