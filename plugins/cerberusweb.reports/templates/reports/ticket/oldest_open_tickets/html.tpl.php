
{if !empty($oldest_tickets)}
	<table cellspacing="0" cellpadding="2" border="0">
	{foreach from=$oldest_tickets key=group_id item=group_tickets}
		{if !empty($group_tickets)}
			<tr>
				<td colspan="3" style="border-bottom:1px solid rgb(200,200,200);padding-right:20px;"><h2>{$groups.$group_id->name}</h2></td>
			</tr>
			
			{foreach from=$group_tickets item=ticket_entry}
				<tr>
					<td style="padding-left:10px;padding-right:20px;">{$ticket_entry->mask}</td>
					<td>{$ticket_entry->subject}</td>
					<td>{$ticket_entry->created_date|date_format:"%Y-%m-%d"}</td>
				</tr>
			{/foreach}
		{/if}
	{/foreach}
	</table>
{/if}

