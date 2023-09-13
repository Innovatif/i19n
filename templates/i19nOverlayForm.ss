$OpenPopupButton

<div class="$ExtraClass open-overlay hide-this">
	<div class="inner">
		<h2>$Title</h2>
		
		$FormFields
		
		<div class="actions">
			<% loop $ActionButtons %>
				$Field
			<% end_loop %>
		</div>
	</div>
</div>