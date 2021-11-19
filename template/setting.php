<div class="wrap">
	<h2><?php echo $page_title; ?></h2>
	<form action="" method="post">
		<?php wp_nonce_field('swagger_api_setting') ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th>API Default namespace</th>
					<td>
						<select name="swagger_api_basepath">
							<?php
							foreach ($namespaces as $namespace) {
								?>
								<option value="<?php echo esc_attr($namespace); ?>" <?php selected($namespace, $swagger_api_basepath) ?>><?php echo esc_html($namespace); ?></option>
							<?php
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th>API Docs</th>
					<td>
						<a href="<?php echo esc_url($docs_url); ?>" target="__blank">Docs URL</a>
					</td>
				</tr>
				<tr>
					<th>API Schema</th>
					<td>
						<a href="<?php echo esc_url($schema_url); ?>" target="__blank">Schema URL</a>
					</td>
				</tr>
				<tr>
					<th>API namespaces list</th>
					<td>
						<a href="<?php echo esc_url($ns_url); ?>" target="__blank">NS List URL</a>
					</td>
				</tr>
				<tr>
					<th>Notes</th>
					<td>
						<ul>
							<li>
								<small>
									Add <code>namespace</code> query parameter to change the default
									(eg <code><?php echo esc_url($docs_url); ?>?namespace=wp/v2</code>)
								</small>
							</li>
						</ul>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button(); ?>
	</form>
</div>