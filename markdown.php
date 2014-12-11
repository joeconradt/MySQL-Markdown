<?php

/**
 * Script for generating markdown (Github specific) from MySQL database metadata
 *
 * How to use:
 * Put this file on a server and access it through a web browser. 
 * Enter the database info and you're good to go
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	$config = $_POST;
	global $markdown;

	markdown_append('## Tables', true);

	try {

		$DB = new PDO(sprintf("mysql:host=%s;dbname=%s", $config['host'], $config['name']), $config['user'], $config['password']);

		$query = $DB->query('SHOW TABLES');

		$tables = $query->fetchAll(PDO::FETCH_ASSOC);

		markdown_append('|Table Name|', true);
		markdown_append('|---|', true);

		foreach($tables as $table) {
			$table = array_values($table)[0];
			
			markdown_append('|' . sprintf('[%s](#%s)', $table, $table) . '|', true);
		}

		foreach($tables as $table) {
			$table = array_values($table)[0];

			markdown_append('', true);
			markdown_append('# ' . $table, true);
			markdown_append('', true);

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

			markdown_append('|' . implode('|', array_keys($results[0])) . '|', true);
			markdown_append('|');

			for($i = 0; $i < count($results[0]); $i++) {
				markdown_append('---|');
			}

			markdown_append('', true);
			
			foreach($results as $result) {
				markdown_append('|' . implode('|', $result) . '|', true);
			}
		}

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
 * @param string $markdown
 */
function markdown_result_template($markdown) {
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

						<h3>Markdown Results</h3>
						<div id="tabs" role="tabpanel">
							<ul class="nav nav-tabs" role="tablist">
								<li role="presentation" class="active"><a href="#preview" role="tab" data-toggle="tab">Preview</a></li>
								<li role="presentation"><a href="#markdown" role="tab" data-toggle="tab">Markdown</a></li>
							</ul>
							<div class="tab-content">
								<div role="tabpanel" class="tab-pane active" id="preview">
									<h4>Loading preview...</h4>
								</div>
								<div role="tabpanel" class="tab-pane" id="markdown"><textarea rows="30" class="form-control"><?php echo $markdown ?></textarea></div>
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




