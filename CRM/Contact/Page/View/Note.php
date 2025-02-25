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
 * Main page for viewing Notes.
 */
class CRM_Contact_Page_View_Note extends CRM_Core_Page {

  /**
   * Notes found running the browse function
   * @var array
   */
  public $values = [];

  /**
   * View details of a note.
   */
  public function view() {
    $note = \Civi\Api4\Note::get()
      ->addSelect('*', 'privacy:label')
      ->addWhere('id', '=', $this->_id)
      ->execute()
      ->single();
    $note['privacy'] = $note['privacy:label'];
    $this->assign('note', $note);

    $comments = CRM_Core_BAO_Note::getNoteTree($this->_id, 1);
    $this->assign('comments', $comments);

    // add attachments part
    $currentAttachmentInfo = CRM_Core_BAO_File::getEntityFile('civicrm_note', $this->_id);
    $this->assign('currentAttachmentInfo', $currentAttachmentInfo);

  }

  /**
   * called when action is browse.
   */
  public function browse(): void {
    $note = new CRM_Core_DAO_Note();
    $note->entity_table = 'civicrm_contact';
    $note->entity_id = $this->getContactID();

    $note->orderBy('modified_date desc');

    //CRM-4418, handling edit and delete separately.
    $permissions = [$this->_permission];
    if ($this->_permission == CRM_Core_Permission::EDIT) {
      //previously delete was subset of edit
      //so for consistency lets grant delete also.
      $permissions[] = CRM_Core_Permission::DELETE;
    }
    $mask = CRM_Core_Action::mask($permissions);

    $this->assign('canAddNotes', CRM_Core_Permission::check('add contact notes'));

    $links = self::links();
    $action = array_sum(array_keys($links)) & $mask;

    $note->find();
    while ($note->fetch()) {
      if (!CRM_Core_BAO_Note::getNotePrivacyHidden($note)) {
        CRM_Core_DAO::storeValues($note, $this->values[$note->id]);

        $this->values[$note->id]['action'] = CRM_Core_Action::formLink($links,
          $action,
          [
            'id' => $note->id,
            'cid' => $this->getContactID(),
          ],
          ts('more'),
          FALSE,
          'note.selector.row',
          'Note',
          $note->id
        );
        if (!empty($note->contact_id)) {
          $contact = new CRM_Contact_DAO_Contact();
          $contact->id = $note->contact_id;
          $contact->find();
          $contact->fetch();
          $this->values[$note->id]['createdBy'] = $contact->display_name;
        }
        $this->values[$note->id]['comment_count'] = CRM_Core_BAO_Note::getChildCount($note->id);

        // paper icon view for attachments part
        $paperIconAttachmentInfo = CRM_Core_BAO_File::paperIconAttachment('civicrm_note', $note->id);
        $this->values[$note->id]['attachment'] = $paperIconAttachmentInfo;
      }
    }
    $this->assign('notes', $this->values);

    $commentLinks = self::commentLinks();

    $action = array_sum(array_keys($commentLinks)) & $mask;

    $commentAction = CRM_Core_Action::formLink($commentLinks,
      $action,
      [
        'id' => $note->id,
        'pid' => $note->entity_id,
        'cid' => $note->entity_id,
      ],
      ts('more'),
      FALSE,
      'note.comment.action',
      'Note',
      $note->id
    );
    $this->assign('commentAction', $commentAction);

    $this->ajaxResponse['tabCount'] = CRM_Contact_BAO_Contact::getCountComponent('note', $this->getContactID());
  }

  /**
   * called when action is update or new.
   */
  public function edit() {
    $controller = new CRM_Core_Controller_Simple('CRM_Note_Form_Note', ts('Contact Notes'), $this->_action);
    $controller->setEmbedded(TRUE);

    // set the userContext stack
    $session = CRM_Core_Session::singleton();
    $contactID = $this->getContactID();
    $url = CRM_Utils_System::url('civicrm/contact/view',
      'action=browse&selectedChild=note&cid=' . $contactID
    );
    $session->pushUserContext($url);

    if (CRM_Utils_Request::retrieve('confirmed', 'Boolean')) {
      $this->delete();
      CRM_Utils_System::redirect($url);
    }

    $controller->reset();
    $controller->set('entityTable', 'civicrm_contact');
    $controller->set('entityId', $this->_contactId);
    $controller->set('id', $this->_id);

    $controller->process();
    $controller->run();
  }

  public function preProcess() {
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);

    if ($this->_id && CRM_Core_BAO_Note::getNotePrivacyHidden($this->_id)) {
      CRM_Core_Error::statusBounce(ts('You do not have access to this note.'));
    }

    $this->_contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
    $this->assign('contactId', $this->_contactId);

    // check logged in url permission
    CRM_Contact_Page_View::checkUserPermission($this);

    $displayName = CRM_Contact_BAO_Contact::displayName($this->_contactId);
    CRM_Utils_System::setTitle(ts('Notes for') . ' ' . $displayName);

    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'browse');
    $this->assign('action', $this->_action);
  }

  /**
   * the main function that is called when the page loads,
   * it decides the which action has to be taken for the page.
   *
   * @return null
   */
  public function run() {
    $this->preProcess();

    if ($this->_action & CRM_Core_Action::VIEW) {
      $this->view();
    }
    elseif ($this->_action & CRM_Core_Action::ADD) {
      if (
        $this->_permission != CRM_Core_Permission::EDIT &&
        !CRM_Core_Permission::check('add contact notes')
        ) {
        CRM_Core_Error::statusBounce(ts('You do not have access to add notes.'));
      }

      $this->edit();
    }
    elseif ($this->_action & CRM_Core_Action::UPDATE) {
      if ($this->_permission != CRM_Core_Permission::EDIT) {
        CRM_Core_Error::statusBounce(ts('You do not have access to edit this note.'));
      }

      $this->edit();
    }
    elseif ($this->_action & CRM_Core_Action::DELETE) {
      if ($this->_permission != CRM_Core_Permission::EDIT) {
        CRM_Core_Error::statusBounce(ts('You do not have access to delete this note.'));
      }
      // we use the edit screen the confirm the delete
      $this->edit();
    }

    $this->browse();
    return parent::run();
  }

  /**
   * Delete the note object from the db and set a status msg.
   */
  public function delete() {
    CRM_Core_BAO_Note::deleteRecord(['id' => $this->_id]);
    $status = ts('Selected Note has been deleted successfully.');
    CRM_Core_Session::setStatus($status, ts('Deleted'), 'success');
  }

  /**
   * Get action links.
   *
   * @return array[]
   */
  public static function links() {
    return [
      CRM_Core_Action::VIEW => [
        'name' => ts('View'),
        'url' => 'civicrm/contact/view/note',
        'qs' => 'action=view&reset=1&cid=%%cid%%&id=%%id%%&selectedChild=note',
        'title' => ts('View Note'),
        'weight' => -20,
      ],
      CRM_Core_Action::UPDATE => [
        'name' => ts('Edit'),
        'url' => 'civicrm/contact/view/note',
        'qs' => 'action=update&reset=1&cid=%%cid%%&id=%%id%%&selectedChild=note',
        'title' => ts('Edit Note'),
        'weight' => -10,
      ],
      CRM_Core_Action::ADD => [
        'name' => ts('Comment'),
        'url' => 'civicrm/contact/view/note',
        'qs' => 'action=add&reset=1&cid=%%cid%%&parentId=%%id%%&selectedChild=note',
        'title' => ts('Add Comment'),
        'weight' => -5,
      ],
      CRM_Core_Action::DELETE => [
        'name' => ts('Delete'),
        'url' => 'civicrm/contact/view/note',
        'qs' => 'action=delete&reset=1&cid=%%cid%%&id=%%id%%&selectedChild=note',
        'title' => ts('Delete Note'),
        'weight' => 100,
      ],
    ];
  }

  /**
   * Get action links for comments.
   *
   * @return array[]
   */
  public static function commentLinks(): array {
    return [
      CRM_Core_Action::VIEW => [
        'name' => ts('View'),
        'url' => 'civicrm/contact/view/note',
        'qs' => 'action=view&reset=1&cid=%%cid%%&id={id}&selectedChild=note',
        'title' => ts('View Comment'),
        'weight' => -20,
      ],
      CRM_Core_Action::UPDATE => [
        'name' => ts('Edit'),
        'url' => 'civicrm/contact/view/note',
        'qs' => 'action=update&reset=1&cid=%%cid%%&id={id}&parentId=%%pid%%&selectedChild=note',
        'title' => ts('Edit Comment'),
        'weight' => -10,
      ],
      CRM_Core_Action::DELETE => [
        'name' => ts('Delete'),
        'url' => 'civicrm/contact/view/note',
        'qs' => 'action=delete&reset=1&cid=%%cid%%&id={id}&selectedChild=note',
        'title' => ts('Delete Comment'),
        'weight' => 100,
      ],
    ];
  }

  /**
   * Get the relevant contact ID.
   *
   * @api supported to be accessed from outside of core.
   *
   * @return int
   *
   * @noinspection PhpUnhandledExceptionInspection
   * @noinspection PhpDocMissingThrowsInspection
   */
  public function getContactID(): int {
    return (int) CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);
  }

}
