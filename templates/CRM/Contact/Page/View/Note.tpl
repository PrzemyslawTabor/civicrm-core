{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
<div class="view-content">
{if $action eq 4}{* when action is view  *}
    {if $notes}
        <div class="crm-block crm-content-block crm-note-view-block">
          <table class="crm-info-panel">
            <tr><td class="label">{ts}Subject{/ts}</td><td>{$note.subject}</td></tr>
            <tr><td class="label">{ts}Date:{/ts}</td><td>{$note.note_date|crmDate}</td></tr>
            <tr><td class="label">{ts}Modified Date:{/ts}</td><td>{$note.modified_date|crmDate}</td></tr>
            <tr><td class="label">{ts}Privacy:{/ts}</td><td>{$note.privacy}</td></tr>
            <tr><td class="label">{ts}Note:{/ts}</td><td>{$note.note|nl2br}</td></tr>

            {if $currentAttachmentInfo}
               {include file="CRM/Form/attachment.tpl"}
            {/if}
          </table>
          <div class="crm-submit-buttons">
            {crmButton class="cancel" icon="times" p='civicrm/contact/view' q="selectedChild=note&reset=1&cid=`$contactId`"}{ts}Done{/ts}{/crmButton}
          </div>

        {if $comments}
        <fieldset>
        <legend>{ts}Comments{/ts}</legend>
            <table class="display">
                <thead>
                    <tr><th>{ts}Comment{/ts}</th><th>{ts}Created By{/ts}</th><th>{ts}Date{/ts}</th><th>{ts}Modified Date{/ts}</th></tr>
                </thead>
                {foreach from=$comments item=comment}
                  <tr class="{cycle values='odd-row,even-row'}"><td>{$comment.note}</td><td>{$comment.createdBy}</td><td>{$comment.note_date}</td><td>{$comment.modified_date}</td></tr>
                {/foreach}
            </table>
        </fieldset>
        {/if}

        </div>
        {/if}
{elseif $action eq 1 or $action eq 2} {* action is add or update *}
  <div class="crm-block crm-form-block crm-note-form-block">
        <table class="form-layout">
            <tr>
                <td class="label">{$form.subject.label}</td>
                <td>
                    {$form.subject.html}
                </td>
            </tr>
            <tr>
              <td class="label">{$form.note_date.label}</td>
              <td>{$form.note_date.html}</td>
            </tr>
            <tr>
                <td class="label">{$form.privacy.label}</td>
                <td>
                    {$form.privacy.html}
                </td>
            </tr>
            <tr>
                <td class="label">{$form.note.label}</td>
                <td>
                    {$form.note.html}
                </td>
            </tr>
            <tr class="crm-activity-form-block-attachment">
                <td colspan="2">
                    {include file="CRM/Form/attachment.tpl"}
                </td>
            </tr>
        </table>

  <div class="crm-section note-buttons-section no-label">
   <div class="content crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
   <div class="clear"></div>
  </div>
    </div>
{/if}
{if ($action eq 8)}
<div class=status>{ts 1=$notes.$id.note}Are you sure you want to delete the note '%1'?{/ts}</div>
<div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location=''}</div>

{/if}

{if ($permission EQ 'edit' OR $canAddNotes) AND ($action eq 16)}
   <div class="action-link">
   <a accesskey="N" href="{crmURL p='civicrm/contact/view/note' q="cid=`$contactId`&action=add"}" class="button medium-popup"><span><i class="crm-i fa-comment" aria-hidden="true"></i> {ts}Add Note{/ts}</span></a>
   </div>
   <div class="clear"></div>
{/if}
<div class="crm-content-block">

{if $notes and $action eq 16}

<script type="text/javascript">
    var commentAction = '{$commentAction|escape:quotes}'

    {literal}
    var commentRows = {};

    function showHideComments( noteId ) {

        elRow = cj('tr#Note-'+ noteId)

        if (elRow.hasClass('view-comments')) {
            cj('tr.note-comment_'+ noteId).remove()
            commentRows['Note-'+ noteId] = {};
            cj('tr#Note-'+ noteId +' span.icon_comments_show').show();
            cj('tr#Note-'+ noteId +' span.icon_comments_hide').hide();
            elRow.removeClass('view-comments');
        } else {
            var getUrl = {/literal}"{crmURL p='civicrm/ajax/rest' h=0}"{literal};
            cj.post(getUrl, { fnName: 'civicrm/note/tree_get', json: 1, id: noteId, sequential: 1 }, showComments, 'json' );
        }

    }

    function showComments (response) {
        var urlTemplate = '{/literal}{crmURL p='civicrm/contact/view' q="reset=1&cid=" h=0 }{literal}'
        if (response['values'][0] && response['values'][0].entity_id) {
            var noteId = response['values'][0].entity_id
            var row = cj('tr#Note-'+ noteId);

            row.addClass('view-comments');

            if (row.hasClass('odd') ) {
                var rowClassOddEven = 'odd'
            } else {
                var rowClassOddEven = 'even'
            }

            if ( commentRows['Note-'+ noteId] ) {
                for ( var i in commentRows['Note-'+ noteId] ) {
                    return false;
                }
            } else {
                commentRows['Note-'+ noteId] = {};
            }
            for (i in response['values']) {
                if ( response['values'][i].id ) {
                    if ( commentRows['Note-'+ noteId] &&
                        commentRows['Note-'+ noteId][response['values'][i].id] ) {
                        continue;
                    }
                    str = '<tr id="Note-'+ response['values'][i].id +'" class="'+ rowClassOddEven +' note-comment_'+ noteId +'">'
                        + '<td></td>'
                        + '<td style="padding-left: 2em">'
                        + response['values'][i].note
                        + '</td><td>'
                        + response['values'][i].subject
                        + '</td><td>'
                        + response['values'][i].note_date
                        + '</td><td>'
                        + response['values'][i].modified_date
                        + '</td><td>'
                        + '<a href="'+ urlTemplate + response['values'][i].createdById +'">'+ response['values'][i].createdBy +'</a>'
                        + '</td><td>'
                        + response['values'][i].attachment
                        + '</td><td>'+ commentAction.replace(/{cid}/g, response['values'][i].createdById).replace(/{id}/g, response['values'][i].id) +'</td></tr>'

                    commentRows['Note-'+ noteId][response['values'][i].id] = str;
                }
            }
            drawCommentRows('Note-'+ noteId);

            cj('tr#Note-'+ noteId +' span.icon_comments_show').hide();
            cj('tr#Note-'+ noteId +' span.icon_comments_hide').show();
        } else {
            CRM.alert('{/literal}{ts escape="js"}There are no comments for this note{/ts}{literal}', '{/literal}{ts escape="js"}None Found{/ts}{literal}', 'alert');
        }

    }

    function drawCommentRows(rowId) {
      if (rowId) {
        row = cj('tr#'+ rowId)
        for (i in commentRows[rowId]) {
            row.after(commentRows[rowId][i]);
            row = cj('tr#Note-'+ i);
        }
      }
    }

    {/literal}
</script>

<div class="crm-results-block">
{* show browse table for any action *}
<div id="notes">
    {strip}
        <table id="options" class="display crm-sortable" data-order='[[3,"desc"]]'>
        <thead>
        <tr>
          <th data-orderable="false"></th>
          <th>{ts}Note{/ts}</th>
          <th>{ts}Subject{/ts}</th>
          <th>{ts}Date{/ts}</th>
          <th>{ts}Modified Date{/ts}</th>
          <th>{ts}Created By{/ts}</th>
          <th data-orderable="false">{ts}Attachment(s){/ts}</th>
          <th data-orderable="false"></th>
        </tr>
        </thead>

        {foreach from=$notes item=note}
        <tr id="Note-{$note.id}" data-action="setvalue" class="{cycle values="odd-row,even-row"} crm-note crm-entity">
            <td class="crm-note-comment">
                {if $note.comment_count}
                    <span id="{$note.id}_show" style="display:block" class="icon_comments_show">
                        <a href="#" onclick="showHideComments({$note.id}); return false;" title="{ts}Show comments for this note.{/ts}"><i class="crm-i fa-caret-right" aria-hidden="true"></i></span></a>
                    </span>
                    <span id="{$note.id}_hide" style="display:none" class="icon_comments_hide">
                        <a href="#" onclick="showHideComments({$note.id}); return false;" title="{ts}Hide comments for this note.{/ts}"><i class="crm-i fa-caret-down" aria-hidden="true"></i></span></a>
                    </span>
                {else}
                    <span class="crm-i fa-caret-right" id="{$note.id}_hide" style="display:none" aria-hidden="true"></span>
                {/if}
            </td>
            <td class="crm-note-note">
                {$note.note|mb_truncate:80:"...":false|nl2br}
                {* Include '(more)' link to view entire note if it has been truncated *}
                {assign var="noteSize" value=$note.note|crmCountCharacters:true}
                {if $noteSize GT 80}
                  <a class="crm-popup" href="{crmURL p='civicrm/contact/view/note' q="action=view&selectedChild=note&reset=1&cid=`$contactId`&id=`$note.id`"}">{ts}(more){/ts}</a>
                {/if}
            </td>
            <td class="crm-note-subject crmf-subject crm-editable">{$note.subject}</td>
            <td class="crm-note-note_date" data-order="{$note.note_date}">{$note.note_date|crmDate}</td>
            <td class="crm-note-modified_date" data-order="{$note.modified_date}">{$note.modified_date|crmDate}</td>
            <td class="crm-note-createdBy">
                <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$note.contact_id`"}">{$note.createdBy}</a>
            </td>
            <td class="crm-note-attachment">
                {foreach from=$note.attachment item=fileinfo}
                  {$fileinfo}
                {/foreach}
            </td>
            <td class="nowrap">{$note.action|replace:'xx':$note.id}</td>
        </tr>
        {/foreach}
        </table>
    {/strip}
 </div>
</div>
{elseif ($action eq 16)}
   <div class="messages status no-popup">
      {icon icon="fa-info-circle"}{/icon}
      {ts}There are no Notes for this contact.{/ts}
   </div>
{/if}
</div>
</div>
