<div class="wrap">
	<h2><?php echo $page_title; ?></h2>
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