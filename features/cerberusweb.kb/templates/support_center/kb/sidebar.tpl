<table cellpadding="0" cellspacing="0" border="0" class="sidebar" id="kb_sidebar">
	<tr>
		<th>{'common.search'|devblocks_translate|capitalize}</th>
	</tr>
	<tr>
		<td>
			<form action="{devblocks_url}c=kb&a=search{/devblocks_url}" method="POST" style="padding-bottom:5px;">
				<input type="text" name="q" value="{$q}" style="width:100%;"><br>
				<select name="scope">
					<option value="all" {if $scope=='all'}selected="selected"{/if}>all words</option>
					<option value="any" {if $scope=='any'}selected="selected"{/if}>any words</option>
					<option value="phrase" {if $scope=='phrase'}selected="selected"{/if}>phrase</option>
				</select>
				<button type="submit">{'common.search'|devblocks_translate|lower}</button>
			</form>
			
			<div style="padding:2px;"><img src="{devblocks_url}c=resource&p=cerberusweb.support_center&f=images/feed-icon-16x16.gif{/devblocks_url}" align="top"> <a href="{devblocks_url}c=rss&m=kb&a=most_popular{/devblocks_url}">Most Popular</a></div>
			<div style="padding:2px;"><img src="{devblocks_url}c=resource&p=cerberusweb.support_center&f=images/feed-icon-16x16.gif{/devblocks_url}" align="top"> <a href="{devblocks_url}c=rss&m=kb&a=new_articles{/devblocks_url}">Recently Added</a></div>
			<div style="padding:2px;"><img src="{devblocks_url}c=resource&p=cerberusweb.support_center&f=images/feed-icon-16x16.gif{/devblocks_url}" align="top"> <a href="{devblocks_url}c=rss&m=kb&a=recent_changes{/devblocks_url}">Recently Updated</a></div>
		</td>
	</tr>
</table>
<br>
