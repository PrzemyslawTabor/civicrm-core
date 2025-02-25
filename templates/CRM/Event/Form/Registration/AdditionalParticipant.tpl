{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
{if $skipCount}
    <h3>Skipped Participant(s): {$skipCount}</h3>
{/if}
{if $action & 1024}
    {include file="CRM/Event/Form/Registration/PreviewHeader.tpl"}
{/if}

{*CRM-4320*}
{if $statusMessage}
    <div class="messages status no-popup">
        <p>{$statusMessage}</p>
    </div>
{/if}

<div class="crm-public-form-item crm-section custom_pre-section">
  {include file="CRM/UF/Form/Block.tpl" fields=$additionalCustomPre}
</div>

{if $priceSet && $allowGroupOnWaitlist}
    {include file="CRM/Price/Form/ParticipantCount.tpl"}
    <div id="waiting-status" style="display:none;" class="messages status no-popup"></div>
    <div class="messages status no-popup" style="width:25%"><span id="event_participant_status"></span></div>
{/if}

<div class="crm-event-id-{$event.id} crm-block crm-event-additionalparticipant-form-block">
{if $priceSet}
     <fieldset id="priceset" class="crm-public-form-item crm-group priceset-group"><legend>{$event.fee_label}</legend>
        {include file="CRM/Price/Form/PriceSet.tpl" extends="Event" hideTotal=false}
    </fieldset>
{else}
    {if $paidEvent}
        <table class="form-layout-compressed">
            <tr class="crm-event-additionalparticipant-form-block-amount">
                <td class="label nowrap">{$event.fee_label} <span class="crm-marker">*</span></td>
                <td>&nbsp;</td>
                <td>{$form.amount.html}</td>
            </tr>
        </table>
    {/if}
{/if}

<div class="crm-public-form-item crm-section custom_post-section">
  {include file="CRM/UF/Form/Block.tpl" fields=$additionalCustomPost}
</div>

<div id="crm-submit-buttons" class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location=''}
</div>
</div>

{if $priceSet && $allowGroupOnWaitlist}
{literal}
<script type="text/javascript">

function allowGroupOnWaitlist( participantCount, currentCount )
{
  var formId          = {/literal}'{$formName}'{literal};
  var waitingMsg      = {/literal}'{$waitingMsg}'{literal};
  var confirmedMsg    = {/literal}'{$confirmedMsg}'{literal};
  var paymentBypassed = {/literal}'{$paymentBypassed}'{literal};

  var availableRegistrations = {/literal}{$availableRegistrations}{literal};
  if ( !participantCount ) participantCount = {/literal}'{$currentParticipantCount}'{literal};
  var totalParticipants = parseInt(participantCount) + parseInt(currentCount);

  var seatStatistics = '{/literal}{ts 1="' + totalParticipants + '"}Total Participants : %1{/ts}{literal}' + '<br />' + '{/literal}{ts 1="' + availableRegistrations + '"}Event Availability : %1{/ts}{literal}';
  cj("#event_participant_status").html( seatStatistics );

  if ( !{/literal}'{$lastParticipant}'{literal} ) return;

  if ( totalParticipants > availableRegistrations ) {

    cj('#waiting-status').show( ).html(waitingMsg);

    if ( paymentBypassed ) {
      cj('input[name=_qf_Participant_'+ formId +'_next]').parent( ).show( );
      cj('input[name=_qf_Participant_'+ formId +'_next_skip]').parent( ).show( );
    }
  } else {
    if ( paymentBypassed ) {
      confirmedMsg += '<br /> ' + paymentBypassed;
    }
    cj('#waiting-status').show( ).html(confirmedMsg);

    if ( paymentBypassed ) {
      cj('input[name=_qf_Participant_'+ formId +'_next]').parent( ).hide( );
      cj('input[name=_qf_Participant_'+ formId +'_next_skip]').parent( ).hide( );
    }
  }
}

</script>
{/literal}
{/if}
