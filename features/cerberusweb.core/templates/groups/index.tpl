<ul class="submenu">
</ul>
<div style="clear:both;"></div>

{if !empty($group)}
<h2 style="color:rgb(74,110,158);">{$group->name}</h2>
{/if}

<table cellpadding="0" cellspacing="5" border="0" width="100%">
	<tr>
		<td width="1%" nowrap="nowrap" valign="top">
		
			<fieldset>
				<legend>{'common.groups'|devblocks_translate|capitalize}</legend>
				
				<table cellpadding="2" cellspacing="2" border="0">
				{foreach from=$groups item=model name=groups key=group_id}
					{assign var=group_member value=$active_worker_memberships.$group_id}
					{if $group_member || $active_worker->is_superuser}
					<tr>
						<td style="padding-right:20px;">
							{if $group_member->is_manager || $active_worker->is_superuser}
								<a href="{devblocks_url}c=groups&gid={$group_id}-{$model->name|devblocks_permalink}{/devblocks_url}"><b>{$model->name}</b></a>
							{else}
								{$model->name}
							{/if}
						</td>
						<td style="padding-right:20px;">
							{if $group_member->is_manager}
								Manager
							{elseif !empty($group_member)}
								Member
							{/if}
						</td>
					</tr>
					{/if}
				{/foreach}
				</table>
			</fieldset>
		</td>
		
		<td width="99%" valign="top">
			{if !empty($group)}
				{include file="devblocks:cerberusweb.core::groups/edit_group.tpl" group=$group}
			{/if}
		</td>
		
	</tr>
</table>
