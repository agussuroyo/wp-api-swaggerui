<div class="wrap">
	<h2><?php echo esc_html( $page_title ); ?></h2>
	<form action="" method="post">
		<?php wp_nonce_field( 'swagger_api_setting' ) ?>
		<table class="form-table">
			<tbody>		
				<tr>
					<th>API Basepath</th>
					<td>
						<select name="swagger_api_basepath">
							<?php
							foreach ( $namespaces as $namespace ) {
								?>
								<option value="<?php echo esc_attr( $namespace ); ?>" <?php selected( $namespace, $swagger_api_basepath ) ?>><?php echo esc_html( $namespace ); ?></option>
								<?php
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Authentication</th>
					<td>
						<label>
							<input type="checkbox" name="swagger_api_auth_schemes[]" value="basic" <?php checked( in_array( 'basic', $swagger_api_auth_schemes, true ) ); ?>>
							Basic
						</label><br>
						<label>
							<input type="checkbox" name="swagger_api_auth_schemes[]" value="bearer" <?php checked( in_array( 'bearer', $swagger_api_auth_schemes, true ) ); ?>>
							Bearer (Authorization header)
						</label>
						<p class="description">Which authentication methods appear in the Swagger UI Authorize dialog. Bearer requires a token plugin (e.g. JWT) installed to validate requests.</p>
					</td>
				</tr>
				<tr>
					<th>OpenAPI Version</th>
					<td>
						<select name="swagger_api_spec_version">
							<?php
							foreach ( $spec_versions as $version ) {
								?>
								<option value="<?php echo esc_attr( $version ); ?>" <?php selected( $version, $swagger_api_spec_version ); ?>><?php echo esc_html( $version ); ?></option>
								<?php
							}
							?>
						</select>
						<p class="description">Schema output format. 2.0 = Swagger 2.0; 3.0.3 = OpenAPI 3.0.3.</p>
					</td>
				</tr>
				<tr>
					<th>API Docs</th>
					<td>
						<a href="<?php echo esc_url( $docs_url ); ?>" target="__blank">Docs URL</a>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button(); ?>
	</form>
</div>