<?php use DecisionRules\AuditRepository;
$details=$event['details_data'];$diff=AuditRepository::diff($details);
$conditionText=static function(array $c):string { $text=trim((string)($c['field_name']??'').' '.(string)($c['operator']??''));if(!in_array($c['operator']??'',['IS_NULL','IS_NOT_NULL'],true))$text.=' '.(string)($c['value']??'');return $text; };
?>
<div class="audit-summary">
<?php if(!empty($details['rule_code'])):?><h3><?=e($details['rule_code'])?></h3><?php endif;?>
<?php if($event['event_type']==='RULE_REPLACED'):?>
  <?php if(!$diff['fields']&&!$diff['conditions']):?><p class="muted">No business field changes were recorded.</p><?php endif;?>
  <?php foreach($diff['fields'] as $change):?><div class="diff-row"><strong><?=e($change['label'])?></strong><span><?=e(is_bool($change['before'])?($change['before']?'Yes':'No'):$change['before'])?> <b>→</b> <?=e(is_bool($change['after'])?($change['after']?'Yes':'No'):$change['after'])?></span></div><?php endforeach;?>
  <?php foreach($diff['conditions'] as $change):?><div class="diff-row condition-diff <?=e($change['type'])?>"><strong>Condition</strong><?php if($change['type']==='changed'):?><span><small>Before</small> <?=e($conditionText($change['before']))?><br><small>After</small> <?=e($conditionText($change['after']))?></span><?php elseif($change['type']==='added'):?><span class="diff-add">+ <?=e($conditionText($change['after']))?></span><?php else:?><span class="diff-remove">− <?=e($conditionText($change['before']))?></span><?php endif;?></div><?php endforeach;?>
<?php elseif($event['event_type']==='RULESET_REJECTED'&&!empty($details['rejection_reason'])):?><div class="callout"><strong>Rejection reason</strong><p><?=nl2br(e($details['rejection_reason']))?></p></div>
<?php else:?>
  <dl class="details-list"><?php foreach($details as $key=>$value):if(in_array($key,['before','after'],true))continue;?><div><dt><?=e(ucwords(str_replace('_',' ',$key)))?></dt><dd><?=e(is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):$value)?></dd></div><?php endforeach;?></dl>
<?php endif;?>
</div>
