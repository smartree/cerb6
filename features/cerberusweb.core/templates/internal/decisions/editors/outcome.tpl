<form id="frmDecisionOutcome{$id}" onsubmit="return false;">
<input type="hidden" name="c" value="internal">
<input type="hidden" name="a" value="">
{if isset($id)}<input type="hidden" name="id" value="{$id}">{/if}
{if isset($parent_id)}<input type="hidden" name="parent_id" value="{$parent_id}">{/if}
{if isset($type)}<input type="hidden" name="type" value="{$type}">{/if}
{if isset($trigger_id)}<input type="hidden" name="trigger_id" value="{$trigger_id}">{/if}

<b>{'common.title'|devblocks_translate|capitalize}:</b><br>
<input type="text" name="title" value="{$model->title}" style="width:100%;"><br>
<br>

{$seq = 0}

{if empty($model->params.groups)}
	<fieldset>
		<legend>
			If <a href="javascript:;">all&#x25be;</a> of these conditions are satisfied
			<a href="javascript:;" onclick="$(this).closest('fieldset').remove();"><span class="cerb-sprite2 sprite-minus-circle"></span></a>
		</legend>
		<input type="hidden" name="nodes[]" value="all">
		
		<ul class="rules" style="margin:0px;list-style:none;padding:0px 0px 2px 0px;"></ul>
	</fieldset>

{else}
	{foreach from=$model->params.groups item=group_data}
	<fieldset>
		<legend>
			If <a href="javascript:;">{if !empty($group_data.any)}any{else}all{/if}&#x25be;</a> of these conditions are satisfied
			<a href="javascript:;" onclick="$(this).closest('fieldset').remove();"><span class="cerb-sprite2 sprite-minus-circle"></span></a>
		</legend>
		<input type="hidden" name="nodes[]" value="{if !empty($group_data.any)}any{else}all{/if}">
		
		<ul class="rules" style="margin:0px;list-style:none;padding:0px 0px 2px 0px;">
			{if isset($group_data.conditions) && is_array($group_data.conditions)}
			{foreach from=$group_data.conditions item=params}
				<li style="padding-bottom:5px;" id="condition{$seq}">
					<input type="hidden" name="nodes[]" value="{$seq}">
					<input type="hidden" name="condition{$seq}[condition]" value="{$params.condition}">
					<a href="javascript:;" onclick="$(this).closest('li').remove();"><span class="cerb-sprite2 sprite-minus-circle"></span></a>
					<b style="cursor:move;">{$conditions.{$params.condition}.label}</b>&nbsp;
					<div style="margin-left:20px;">
						{$event->renderCondition({$params.condition},$trigger,$params,$seq)}
					</div>
				</li>
				{$seq = $seq + 1}
			{/foreach}
			{/if}
		</ul>
	</fieldset>
	{/foreach}
{/if}

<div id="divDecisionOutcomeToolbar{$id}" style="display:none;">
	<button type="button" class="cerb-popupmenu-trigger" onclick="">Insert &#x25be;</button>
	<button type="button" class="tester">{'common.test'|devblocks_translate|capitalize}</button>
	<div class="tester"></div>
	<ul class="cerb-popupmenu" style="max-height:200px;overflow-y:auto;">
		<li style="background:none;">
			<input type="text" size="18" class="input_search filter">
		</li>
		{$types = $values._types}
		{foreach from=$labels key=k item=v}
			{$modifier = ''}
			
			{$type = $types.$k}
			{if $type == Model_CustomField::TYPE_DATE}
				{$modifier = '|date'}
			{/if}
			<li><a href="javascript:;" token="{$k}{$modifier}">{$v}</a></li>
		{/foreach}
	</ul>
</div>

</form>

<form id="frmDecisionOutcomeAdd{$id}" action="javascript:;" onsubmit="return false;">
<input type="hidden" name="seq" value="{$seq}">
<input type="hidden" name="condition" value="">
{if isset($trigger_id)}<input type="hidden" name="trigger_id" value="{$trigger_id}">{/if}
<fieldset>
	<legend>Add Condition</legend>

	<span class="cerb-sprite2 sprite-plus-circle"></span>
	<button type="button" class="condition cerb-popupmenu-trigger">Add Condition &#x25be;</button>
	<button type="button" class="group">Add Group</button>
	<ul class="cerb-popupmenu" style="border:0;">
		<li style="background:none;">
			<input type="text" size="16" class="input_search filter">
		</li>
		{foreach from=$conditions key=token item=condition}
		<li><a href="javascript:;" token="{$token}">{$condition.label}</a></li>
		{/foreach}
	</ul>
</fieldset>
</form>

{if isset($id)}
<fieldset class="delete" style="display:none;">
	<legend>Delete this outcome?</legend>
	<p>Are you sure you want to permanently delete this outcome and its children?</p>
	<button type="button" class="green" onclick="genericAjaxPost('frmDecisionOutcome{$id}','','c=internal&a=saveDecisionDeletePopup',function() { genericAjaxPopupDestroy('node_outcome{$id}'); genericAjaxGet('decisionTree{$trigger_id}','c=internal&a=showDecisionTree&id={$trigger_id}'); });"> {'common.yes'|devblocks_translate|capitalize}</button>
	<button type="button" class="red" onclick="$(this).closest('fieldset').hide().next('form.toolbar').show();"> {'common.no'|devblocks_translate|capitalize}</button>
</fieldset>
{/if}

{$status_div = "status_{uniqid()}"}

<form class="toolbar">
	{if !isset($id)}
		<button type="button" onclick="genericAjaxPost('frmDecisionOutcome{$id}','','c=internal&a=saveDecisionPopup',function() { genericAjaxPopupDestroy('node_outcome{$id}'); genericAjaxGet('decisionTree{$trigger_id}','c=internal&a=showDecisionTree&id={$trigger_id}'); });"><span class="cerb-sprite2 sprite-tick-circle"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
	{else}
		<button type="button" onclick="genericAjaxPost('frmDecisionOutcome{$id}','','c=internal&a=saveDecisionPopup',function() { genericAjaxPopupDestroy('node_outcome{$id}'); genericAjaxGet('decisionTree{$trigger_id}','c=internal&a=showDecisionTree&id={$trigger_id}'); });"><span class="cerb-sprite2 sprite-folder-tick-circle"></span> {'common.save_and_close'|devblocks_translate|capitalize}</button>
		<button type="button" onclick="genericAjaxPost('frmDecisionOutcome{$id}','','c=internal&a=saveDecisionPopup',function() { Devblocks.showSuccess('#{$status_div}', 'Saved!'); genericAjaxGet('decisionTree{$trigger_id}','c=internal&a=showDecisionTree&id={$trigger_id}'); });"><span class="cerb-sprite2 sprite-tick-circle"></span> {'common.save_and_continue'|devblocks_translate|capitalize}</button>
		<button type="button" onclick="genericAjaxPopup('simulate_behavior','c=internal&a=showBehaviorSimulatorPopup&trigger_id={$trigger_id}','reuse',false,'500');"> <span class="cerb-sprite2 sprite-gear"></span> Simulator</button>
		<button type="button" onclick="$(this).closest('form').hide().prev('fieldset.delete').show();"><span class="cerb-sprite2 sprite-cross-circle"></span> {'common.delete'|devblocks_translate|capitalize}</button>
	{/if}
</form>

<div id="{$status_div}" style="display:none;"></div>

<script type="text/javascript">
	$popup = genericAjaxPopupFetch('node_outcome{$id}');
	$popup.one('popup_open', function(event,ui) {
		$(this).dialog('option','title',"{if empty($id)}New {/if}Outcome");
		$(this).find('input:text').first().focus();

		var $frm = $popup.find('form#frmDecisionOutcome{$id}');
		var $legend = $popup.find('fieldset legend');
		var $menu = $popup.find('fieldset ul.cerb-popupmenu:first'); 

		$frm.find('fieldset UL.rules')
			.sortable({ 'items':'li', 'placeholder':'ui-state-highlight', 'handle':'> b', 'connectWith':'#frmDecisionOutcome{$id} fieldset ul.rules' })
			;

		var $funcGroupAnyToggle = function(e) {
			$any = $(this).closest('fieldset').find('input:hidden:first');
			
			if("any" == $any.val()) {
				$(this).html("all&#x25be;");
				$any.val('all');
			} else {
				$(this).html("any&#x25be;");
				$any.val('any');
			}
		}
		
		$legend.find('a').click($funcGroupAnyToggle);

		$popup.find('BUTTON.chooser_worker.unbound').each(function() {
			seq = $(this).closest('fieldset').find('input:hidden[name="conditions[]"]').val();
			ajax.chooser(this,'cerberusweb.contexts.worker','condition'+seq+'[worker_id]', { autocomplete:true });
			$(this).removeClass('unbound');
		});

		$frmAdd = $popup.find('#frmDecisionOutcomeAdd{$id}');

		$frmAdd.find('button.group')
			.click(function(e) {
				$group = $('<fieldset></fieldset>');
				$group.append('<legend>If <a href="javascript:;">all&#x25be;</a> of these conditions are satisfied <a href="javascript:;" onclick="$(this).closest(\'fieldset\').remove();"><span class="cerb-sprite2 sprite-minus-circle"></span></a></legend>');
				$group.append('<input type="hidden" name="nodes[]" value="all">');
				$group.append('<ul class="rules" style="margin:0px;list-style:none;padding:0px;padding-bottom:5px;"></ul>');
				$group.find('legend > a').click($funcGroupAnyToggle);
				$frm.append($group);

				$frm.find('fieldset UL.rules')
					.sortable({ 'items':'li', 'placeholder':'ui-state-highlight', 'handle':'> b', 'connectWith':'#frmDecisionOutcome{$id} fieldset ul.rules' })
					;
			})
			;
		
		// Placeholders
		
		$popup.delegate(':text.placeholders, textarea.placeholders', 'focus', function(e) {
			$toolbar = $('#divDecisionOutcomeToolbar{$id}');
			src = (null==e.srcElement) ? e.target : e.srcElement;
			if(0 == $(src).nextAll('#divDecisionOutcomeToolbar{$id}').length) {
				$toolbar.find('div.tester').html('');
				$toolbar.find('ul.cerb-popupmenu').hide();
				$toolbar.show().insertAfter(src);
			}
		});
		
		// Placeholder menu
		
		$divPlaceholderMenu = $('#divDecisionOutcomeToolbar{$id}');
		
		$ph_menu_trigger = $divPlaceholderMenu.find('button.cerb-popupmenu-trigger');
		$ph_menu = $divPlaceholderMenu.find('ul.cerb-popupmenu');
		$ph_menu_trigger.data('menu', $ph_menu);
		
		$divPlaceholderMenu.find('button.tester').click(function(e) {
			var divTester = $(this).nextAll('div.tester').first();
			
			$toolbar = $('DIV#divDecisionOutcomeToolbar{$id}');
			$field = $toolbar.prev(':text, textarea');
			
			if(null == $field)
				return;
			
			regexpName = /^(.*?)\[(.*?)\]$/;
			hits = regexpName.exec($field.attr('name'));
			
			if(null == hits || hits.length < 3)
				return;
			
			strNamespace = hits[1];
			strName = hits[2];
			
			genericAjaxPost($(this).closest('form').attr('id'), divTester, 'c=internal&a=testDecisionEventSnippets&prefix=' + strNamespace + '&field=' + strName);
		});
		
		$ph_menu_trigger
			.click(
				function(e) {
					$ph_menu = $(this).data('menu');
					
					if($ph_menu.is(':visible')) {
						$ph_menu.hide();
						
					} else {
						$ph_menu
							.show()
							.find('> li input:text')
							.focus()
							.select()
							;
					}
				}
			)
			.bind('remove',
				function(e) {
					$ph_menu = $(this).data('menu');
					$ph_menu.remove();
				}
			)
		;
		
		$ph_menu.find('> li > input.filter').keyup(
			function(e) {
				term = $(this).val().toLowerCase();
				$ph_menu = $(this).closest('ul.cerb-popupmenu');
				$ph_menu.find('> li a').each(function(e) {
					if(-1 != $(this).html().toLowerCase().indexOf(term)) {
						$(this).parent().show();
					} else {
						$(this).parent().hide();
					}
				});
			}
		);
		
		$ph_menu.find('> li').click(function(e) {
			e.stopPropagation();
			if(!$(e.target).is('li'))
				return;
		
			$(this).find('a').trigger('click');
		});
		
		$ph_menu.find('> li > a').click(function() {
			$toolbar = $('DIV#divDecisionOutcomeToolbar{$id}');
			$field = $toolbar.prev(':text, textarea');
			
			if(null == $field)
				return;
			
			strtoken = $(this).attr('token');
			
			$field.focus().insertAtCursor('{literal}{{{/literal}' + strtoken + '{literal}}}{/literal}');
		});

		// Quick insert condition menu

		$menu_trigger = $frmAdd.find('button.condition.cerb-popupmenu-trigger');
		$menu = $frmAdd.find('ul.cerb-popupmenu');
		$menu_trigger.data('menu', $menu);

		$menu_trigger
			.click(
				function(e) {
					$menu = $(this).data('menu');

					if($menu.is(':visible')) {
						$menu.hide();
						return;
					}
					
					$menu
						.show()
						.find('> li input:text')
						.focus()
						.select()
						;
				}
			)
		;

		$menu.find('> li > input.filter').keyup(
			function(e) {
				term = $(this).val().toLowerCase();
				$menu = $(this).closest('ul.cerb-popupmenu');
				$menu.find('> li a').each(function(e) {
					if(-1 != $(this).html().toLowerCase().indexOf(term)) {
						$(this).parent().show();
					} else {
						$(this).parent().hide();
					}
				});
			}
		);

		$menu.find('> li').click(function(e) {
			e.stopPropagation();
			if(!$(e.target).is('li'))
				return;

			$(this).find('a').trigger('click');
		});

		$menu.find('> li > a').click(function() {
			token = $(this).attr('token');
			$frmDecAdd = $('#frmDecisionOutcomeAdd{$id}');
			$frmDecAdd.find('input[name=condition]').val(token);
			$this = $(this);
			
			genericAjaxPost('frmDecisionOutcomeAdd{$id}','','c=internal&a=doDecisionAddCondition',function(html) {
				$ul = $('#frmDecisionOutcome{$id} UL.rules:last');
				
				seq = parseInt($frmDecAdd.find('input[name=seq]').val());
				if(null == seq)
					seq = 0;

				$html = $('<div style="margin-left:20px;">' + html + '</div>');
				
				$container = $('<li style="padding-bottom:5px;" id="condition'+seq+'"></li>');
				$container.append('<input type="hidden" name="nodes[]" value="' + seq + '">');
				$container.append('<input type="hidden" name="condition'+seq+'[condition]" value="' + token + '">');
				$container.append('<a href="javascript:;" onclick="$(this).closest(\'li\').remove();"><span class="cerb-sprite2 sprite-minus-circle"></span></a> ');
				$container.append('<b style="cursor:move;">' + $this.text() + '</b>&nbsp;');

				$ul.append($container);
				$container.append($html);

				$html.find('BUTTON.chooser_worker.unbound').each(function() {
					ajax.chooser(this,'cerberusweb.contexts.worker','condition'+seq+'[worker_id]', { autocomplete:true });
					$(this).removeClass('unbound');
				});
				
				$menu.find('input:text:first').focus().select();

				$frmDecAdd.find('input[name=seq]').val(1+seq);
			});
		});

	}); // end popup_open
</script>
