<?php

/**
 * Script for generating markdown (Github specific) from MySQL database metadata
 *
 * How to use:
 * Put this file on a server and access it through a web browser. 
 * Enter the database info and you're good to go
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	global $markdown, $markdown_html, $config;

	$config = $_POST;

	markdown_append('## Tables', true);

	try {

		$DB = new PDO(sprintf("mysql:host=%s;dbname=%s", $config['host'], $config['name']), $config['user'], $config['password']);

		$table = $config['name'];
		$query = $DB->prepare('SELECT table_name, table_comment FROM INFORMATION_SCHEMA.TABLES WHERE table_schema = :table');
		$query->bindParam(':table', $table, PDO::PARAM_STR);
		$query->execute();

		$tables = $query->fetchAll(PDO::FETCH_ASSOC);

		markdown_format_table_list_markdown($tables);
		markdown_format_table_list_html($tables);

		foreach($tables as $table) {
			$table = array_values($table)[0];

			markdown_format_table_heading_markdown($table);
			markdown_format_table_heading_html($table);

			$info_sql = 'SHOW FULL COLUMNS FROM ' . $table;

			$query = $DB->prepare($info_sql);
			$query->execute();

			$results = $query->fetchAll(PDO::FETCH_ASSOC);

			foreach($results as &$result) {
				foreach($result as $column_name => $column) {
					if(!in_array($column_name, $config['columns'])) {
						unset($result[$column_name]);
					}
				}
			}

			markdown_format_table_markdown($results);
			markdown_format_table_html($results);
		}
		$now = 'Documentation generated at ' . (new DateTime('now'))->format('Y-m-d g:i:s');
		markdown_append($now);
		markdown_append_html($now);

	} catch(Exception $e) {
		print_r($e);
	}

	markdown_result_template($markdown);

} else {
	markdown_form_template();
}

/**
 * @param string $string
 * @param bool $newline
 */
function markdown_append($string, $newline = false) {
	global $markdown;
	$markdown .= $string;
	if($newline) {
		$markdown .= PHP_EOL;
	}
}

/**
 * @param string $string
 * @param bool $newline
 */
function markdown_append_html($string, $newline = false) {
	global $markdown_html;
	$markdown_html .= $string;
	if($newline) {
		$markdown_html .= PHP_EOL;
	}
}

/**
 * @param array $tables List of table names
 */
function markdown_format_table_list_markdown($tables) {
	markdown_append('|Table Name|Table Comment', true);
	markdown_append('|---|---|', true);

	foreach($tables as $table) {
		
		markdown_append('|' . sprintf('[%s](#%s)', $table['table_name'], $table['table_name']) . '|');
		markdown_append($table['table_comment'] . '|', true);
	}
}

/**
 * @param string $table Table name
 */
function markdown_format_table_heading_markdown($table) {
	markdown_append('', true);
	markdown_append('# ' . $table, true);
	markdown_append('', true);
}

/**
 * @param array $data Results from SHOW FULL COLUMNS
 */
function markdown_format_table_markdown($data) {

	global $config;

	markdown_append('|' . implode('|', array_keys($data[0])) . '|', true);
	markdown_append('|');

	for($i = 0; $i < count($data[0]); $i++) {
		markdown_append('---|');
	}

	markdown_append('', true);
	
	foreach($data as $result) {
		markdown_append('|');
		foreach($result as $column_name => $column_data) {
			if($config['bold_field'][0] == 1 && $column_name == 'Field') {
				$column_data = '**' . $column_data . '**';
			}
			markdown_append($column_data . '|');
		}
		markdown_append('', true);
	}
}

/**
 * @param array $tables List of table names
 */
function markdown_format_table_list_html($tables) {
	markdown_append_html('<table>', true);
	markdown_append_html('<thead>', true);
	markdown_append_html('<tr><th>Table Name</th><th>Table Comment</th></tr>', true);
	markdown_append_html('</thead>', true);

	markdown_append_html('<tbody>', true);
	foreach($tables as $table) {
		markdown_append_html('<tr>', true);
		markdown_append_html('<td>' . sprintf('<a href="#%s">%s</a>', $table['table_name'], $table['table_name']) . '</td>', true);
		markdown_append_html('<td>' . $table['table_comment'] . '</td>', true);
		markdown_append_html('</tr>', true);
	}
	markdown_append_html('</tbody>', true);
	markdown_append_html('</table>', true);
}

/**
 * @param string $table Table name
 */
function markdown_format_table_heading_html($table) {
	markdown_append_html(sprintf('<h1 id="%s">%s</h1>', $table, $table), true);
}

/**
 * @param array $data Results from SHOW FULL COLUMNS
 */
function markdown_format_table_html($data) {

	global $config;

	$comments = array();

	if($config['comment_row'][0] == 1) {
		foreach($data as $key => &$result) {
			if(array_key_exists('Comment', $result)) {
				$comment = $result['Comment'];
				$comments[$key] = $comment;
				unset($result['Comment']);
			}
		}
	}

	markdown_append_html('<table>', true);
	markdown_append_html('<thead>', true);
	markdown_append_html('<tr><th>' . implode('</th><th>', array_keys($data[0])) . '</th></tr>', true);
	markdown_append_html('</thead>', true);

	markdown_append_html('<tbody>', true);
	
	foreach($data as $key => $result) {
		markdown_append_html('<tr>', true);

		foreach($result as $column_key => $column_data) {
			if($config['bold_field'][0] == 1 && $column_key == 'Field') {
				$column_data = '<b>' . $column_data . '</b>';
			}
			markdown_append_html('<td>' . $column_data . '</td>',true);
		}

		markdown_append_html('</tr>', true);

		if($config['comment_row'][0] == 1 && !empty($comments[$key])) {
			markdown_append_html('<tr><td colspan="' . count($result) . '">' . $comments[$key] . '</td></tr>', true);
		}
	}

	markdown_append_html('</tbody>', true);
	markdown_append_html('</table>', true);
}

/**
 * @param string $markdown
 */
function markdown_result_template($markdown) {
	global $markdown_html, $config;
?><!DOCTYPE html>
<html>
<head>
	<title>MySQL Markdown</title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap-theme.min.css">
	<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
	<script>
		$(document).ready(function() {
			$('#tabs a').click(function(e) {
				e.preventDefault()
				$(this).tab('show')
			});

			$.ajax({
				type: "POST",
				dataType: "html",
				processData: false,
				url: "https://api.github.com/markdown/raw",
				data: $("textarea").val(),
				contentType: "text/plain",
				success: function(data) {
					if(!data) {
						$("#preview").html("<h4>No preview available</h4>");
					}
					$("#preview").html(data);
					$("#preview table").addClass('table');
				}, 
				error: function(jqXHR, textStatus, error){
					$("#preview").html("<h4>No preview available</h4>");
					console.log(jqXHR, textStatus, error);
				}
			});

			$("#html_preview table").addClass("table");
		});
	</script>
</head>
<body>
	<div class="container" style="margin-top:40px">
		<div class="row">
			<div class="col-md-10 col-md-offset-1">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h2 class="panel-title">MySQL Markdown Documentation</h2>
					</div>
					<div class="panel-body">

						<a href="markdown.php" class="btn btn-default">Back</a>
						<a href="#" onclick="location.reload(true)" class="btn btn-default">Regenerate</a>
						<?php if($config['comment_row']) : ?>
							<br><br>
							<p class="alert alert-warning"><b>Note:</b> Comments on separate rows will only appear in the HTML version. The Markdown version will have the comment column included as normal.</p>
						<?php endif; ?>
						<h3>Markdown Results</h3>
						<div id="tabs" role="tabpanel">
							<ul class="nav nav-tabs" role="tablist">
								<li role="presentation" class="active"><a href="#preview" role="tab" data-toggle="tab">Preview</a></li>
								<li role="presentation"><a href="#markdown" role="tab" data-toggle="tab">Markdown</a></li>
								<li role="presentation"><a href="#html" role="tab" data-toggle="tab">HTML</a></li>
								<li role="presentation"><a href="#html_preview" role="tab" data-toggle="tab">HTML Preview</a></li>
							</ul>
							<div class="tab-content">
								<div role="tabpanel" class="tab-pane active" id="preview">
									<h4>Loading preview...</h4>
								</div>
								<div role="tabpanel" class="tab-pane" id="markdown"><textarea rows="30" class="form-control"><?php echo $markdown ?></textarea></div>
								<div role="tabpanel" class="tab-pane" id="html"><textarea rows="30" class="form-control"><?php echo $markdown_html ?></textarea></div>
								<div role="tabpanel" class="tab-pane" id="html_preview"><?php echo $markdown_html ?></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>

<?php
}

function markdown_form_template() {
?><!DOCTYPE html>
<html>
<head>
	<title>MySQL Markdown</title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap-theme.min.css">
	<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
</head>
<body>
	<div class="container" style="margin-top:40px">
		<div class="row">
			<div class="col-md-10 col-md-offset-1">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h2 class="panel-title">MySQL Markdown Documentation</h2>
					</div>
					<div class="panel-body">
						<form class="form-horizontal" role="form" method="post">
							<div class="form-group">
								<label class="col-sm-3 control-label">Database Host</label>
								<div class="col-sm-9">
									<input type="text" name="host" class="form-control" placeholder="localhost">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Database Name</label>
								<div class="col-sm-9">
									<input type="text" name="name" class="form-control" placeholder="">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Database User</label>
								<div class="col-sm-9">
									<input type="text" name="user" class="form-control" placeholder="">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Database Password</label>
								<div class="col-sm-9">
									<input type="password" name="password" class="form-control" placeholder="">
								</div>
							</div>
							<hr>
							<h3>Options</h3>
							<div class="form-group">
								<label class="col-sm-3 control-label">Columns</label>
								<div class="col-sm-9">
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Field" checked="checked"> Field
									</label>
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Type" checked="checked"> Type
									</label>
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Collation"> Collation
									</label>
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Null" checked="checked"> Null
									</label>
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Key" checked="checked"> Key
									</label>
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Default" checked="checked"> Default
									</label>
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Extra"> Extra
									</label>
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Privileges"> Privileges
									</label>
									<label class="checkbox-inline">
										<input type="checkbox" name="columns[]" value="Comment" checked="checked"> Comment
									</label>
								</div>	
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Bold Field names</label>
								<div class="col-sm-9">
									<label class="radio-inline">
										<input type="radio" name="bold_field[]" value="1"> Yes
									</label>
									<label class="radio-inline">
										<input type="radio" name="bold_field[]" value="0" checked="checked"> No
									</label>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Separate row for comments (HTML version only)</label>
								<div class="col-sm-9">
									<label class="radio-inline">
										<input type="radio" name="comment_row[]" value="1"> Yes
									</label>
									<label class="radio-inline">
										<input type="radio" name="comment_row[]" value="0" checked="checked"> No
									</label>
								</div>
							</div>
							<div class="form-group">
								<div class="col-sm-offset-3 col-sm-9">
									<button type="submit" class="btn btn-default">Generate Markdown</button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html><?php
}

?>




