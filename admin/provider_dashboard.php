<?php

set_time_limit(0);
ignore_user_abort(true);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');


include "session.php"; include "functions.php";
if ($rPermissions["is_admin"]){
	$secure = true;
}elseif($rPermissions["reset_stb_data"]){
	$secure = true;
}else{
	$secure = false;
}

if (!$secure) { header("Location: ./reseller.php"); exit(); }

//if (!$rPermissions["is_admin"]) { header("Location: ./reseller.php"); }

if ($rAdminSettings["dark_mode"]) {
	$rColours = Array(1 => Array("secondary", "#7e8e9d"), 2 => Array("secondary", "#7e8e9d"), 3 => Array("secondary", "#7e8e9d"), 4 => Array("secondary", "#7e8e9d"));
} else {
	$rColours = Array(1 => Array("purple", "#675db7"), 2 => Array("success", "#23b397"), 3 => Array("pink", "#e36498"), 4 => Array("info", "#56C3D6"));
}





// If form submitted
if (isset($_POST['provider_id'])) {
    header('Content-Type: application/json; charset=utf-8');

    set_time_limit(0);
    ignore_user_abort(true);

    $provider_id = (int)$_POST['provider_id'];
    $time = date('Y-m-d H:i:s');

    // Get Provider
    $getprovider = $db->query("SELECT * FROM providers WHERE id='$provider_id'");

    if (!$getprovider || !$getprovider->num_rows) {
        echo json_encode(array(
            "valid" => false,
            "message" => "Provider not found."
        ));
        exit();
    }

    $providerrow = $getprovider->fetch_assoc();

    $provider = $providerrow['name'];
    $dns      = rtrim($providerrow['dns'], '/');
    $username = $providerrow['username'];
    $password = $providerrow['password'];
	
	
	
	// Get Live Categories
	$cat_api = $dns . "/player_api.php?username=" .
			   urlencode($username) .
			   "&password=" .
			   urlencode($password) .
			   "&action=get_live_categories";

	$ch = curl_init();

	curl_setopt_array($ch, array(
		CURLOPT_URL => $cat_api,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_CONNECTTIMEOUT => 20,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_USERAGENT => "Mozilla/5.0"
	));

	$cat_json = curl_exec($ch);
	curl_close($ch);

	$category_map = array();

	$categories = json_decode($cat_json, true);

	if (is_array($categories)) {
		foreach ($categories as $cat) {
			if (isset($cat['category_id'])) {
				$category_map[(string)$cat['category_id']] = (string)($cat['category_name'] ?? '');
			}
		}
	}
	
	

    // Build Live API URL
    $api = $dns . "/player_api.php?username=" .
           urlencode($username) .
           "&password=" .
           urlencode($password) .
           "&action=get_live_streams";

    // CURL
    $ch = curl_init();

    curl_setopt_array($ch, array(
        CURLOPT_URL => $api,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => "Mozilla/5.0"
    ));

    $json = curl_exec($ch);

    if (curl_errno($ch)) {

        echo json_encode(array(
            "valid" => false,
            "message" => curl_error($ch)
        ));

        curl_close($ch);
        exit();
    }

    curl_close($ch);

    if (!$json) {

        echo json_encode(array(
            "valid" => false,
            "message" => "Unable to download live streams."
        ));

        exit();
    }

    $streams = json_decode($json, true);

    if (!is_array($streams)) {

        echo json_encode(array(
            "valid" => false,
            "message" => "Invalid API Response."
        ));

        exit();
    }

    $providerEscaped = $db->real_escape_string($provider);
    $rows = [];

    foreach ($streams as $stream) {

        if (!isset($stream['stream_id'])) {
            continue;
        }
		
		$category_name = '';

		if (isset($stream['category_id']) && isset($category_map[$stream['category_id']])) {
			$category_name = $db->real_escape_string($category_map[$stream['category_id']]);
		}

        if (isset($stream['stream_type']) && strtolower($stream['stream_type']) != "live") {
            continue;
        }

        $stream_id   = (int)$stream['stream_id'];
        $epg_id      = $db->real_escape_string($stream['epg_channel_id'] ?? '');
        $stream_name = $db->real_escape_string($stream['name'] ?? '');
        $image       = $db->real_escape_string($stream['stream_icon'] ?? '');

        $stream_url = $dns .
            "/live/" .
            rawurlencode($username) .
            "/" .
            rawurlencode($password) .
            "/" .
            $stream_id .
            ".ts";

        $rows[] = "('{$stream_id}','{$epg_id}','{$stream_name}','{$category_name}','{$image}','{$providerEscaped}','" . $db->real_escape_string($stream_url) . "')";
    }

    try {
        $db->begin_transaction();
        $db->query("DELETE FROM providers_streams WHERE provider='{$providerEscaped}'");
        $count = 0;
        foreach (array_chunk($rows, 500) as $batch) {
            $db->query("INSERT IGNORE INTO providers_streams (stream_id, epg_id, stream_name, stream_category, stream_image, provider, stream_url) VALUES " . implode(',', $batch));
            $count += $db->affected_rows;
        }
        $db->query("UPDATE providers SET downloaded=1, download_time='" . $db->real_escape_string($time) . "' WHERE id={$provider_id}");
        $db->commit();
    } catch (mysqli_sql_exception $e) {
        $db->rollback();
        echo json_encode(["valid" => false, "message" => "Import failed: " . $e->getMessage()]);
        exit();
    }

    echo json_encode(array(
        "valid" => true,
        "message" => $provider . " Imported Successfully (" . $count . " Live Channels)"
    ));

    $db->close();
    exit();
}





$providerRows = [];
$providerResponses = [];
$getprovider = $db->query("SELECT * FROM `providers` WHERE `is_active` != 0 ORDER BY `priority` ASC");
while ($getprovider && $row = $getprovider->fetch_assoc()) {
    $providerRows[] = $row;
}

// Provider status endpoints are independent, so fetch them concurrently.
$multiHandle = curl_multi_init();
$providerHandles = [];
foreach ($providerRows as $row) {
    if (empty($row['url'])) {
        continue;
    }
    $handle = curl_init();
    curl_setopt_array($handle, [
        CURLOPT_URL => $row['url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
    ]);
    curl_multi_add_handle($multiHandle, $handle);
    $providerHandles[(int)$row['id']] = $handle;
}
do {
    $status = curl_multi_exec($multiHandle, $running);
    if ($running) {
        curl_multi_select($multiHandle, 1.0);
    }
} while ($running && $status === CURLM_OK);
foreach ($providerHandles as $providerID => $handle) {
    if (curl_errno($handle) === 0 && curl_getinfo($handle, CURLINFO_HTTP_CODE) < 400) {
        $decoded = json_decode(curl_multi_getcontent($handle), true);
        if (is_array($decoded)) {
            $providerResponses[$providerID] = $decoded;
        }
    }
    curl_multi_remove_handle($multiHandle, $handle);
    curl_close($handle);
}
curl_multi_close($multiHandle);

if ($rSettings["sidebar"]) {
    include "header_sidebar.php";
} else {
    include "header.php";
}
        if ($rSettings["sidebar"]) { ?>
        <div class="content-page"><div class="content"><div class="container-fluid">
        <?php } else { ?>
        <div class="wrapper"><div class="container-fluid">
        <?php } ?>
				<?php //if (hasPermissions("adv", "index")) { 
				if ($secure) {
				?>
                
                <div class="tab-content">
                    <div class="tab-pane show active" id="server-home">
                        <div class="row">
							
							<?php 
							 foreach($providerRows as $getproviderrow){
							 $to_time = time();
							 $from_time = strtotime($getproviderrow['download_time'] ?? '') ?: $to_time;
							 $userjson = $providerResponses[(int)$getproviderrow['id']] ?? [];
							 $userInfo = is_array($userjson['user_info'] ?? null) ? $userjson['user_info'] : [];
							 $connectionsallowed = $userInfo['max_connections'] ?? 0;
							 $connectionsactive = $userInfo['active_cons'] ?? 0;

									if ( $connectionsallowed == '0'  ) {
										$userjson02 = "Unlimited";
									} else {
										$userjson02 = $connectionsallowed;
									}

									$expire = $userInfo['exp_date'] ?? '';
									if ( $expire === '' || $expire === null || $expire == 0 ) {
										$finalex = "Unlimited";
									} else {
										$finalex = gmdate("d M Y @ H:ia", (int)$expire);
									}
							 
							 ?>
							<div class="provider-card col-xl-2 col-md-4">
								<div class="card-header bg-purple py-3 text-white">									
									<h5 class="card-title mb-0 text-white"><?=htmlspecialchars(strtoupper($getproviderrow["name"]), ENT_QUOTES, 'UTF-8')?> <span><?=htmlspecialchars($finalex, ENT_QUOTES, 'UTF-8')?></span></h5>
								</div>
								<div class="card-header py-3 text-white<?php if (!$rAdminSettings["dark_mode"]) { echo " bg-white"; } ?>">
									<div class="row">
										<div class="col-md-6" align="center">
											<h4 class="header-title"><?=$_["conns"]?></h4>
											<p class="sub-header" id=""><?=$userjson02?></p>
										</div>										
										<div class="col-md-6" align="center">
											<h4 class="header-title"><?=$_["online"]?></h4>
											<p class="sub-header" id=""><?=$connectionsactive?></p>
										</div>
									</div>
									<div class="row" style="">
										<div class="col-md-12" align="center">
											<h4><?php echo round(abs($to_time - $from_time) / 60,2). " minute"; ?></h4>	
										</div>
										<button type="button" id="<?=$getproviderrow["id"]?>" class="process-provider btn btn-primary waves-effect waves-light btn-xl" style="margin:auto;">Process Provider</button>										
									</div>									
								</div>		
								<br>
							</div>
							<?php } ?>
                        </div>
                    </div>
                </div>
                <!-- end row -->
				<?php } else { ?>
				<div class="alert alert-danger show text-center" role="alert" style="margin-top:20px;">
					<?=$_["dashboard_no_permissions"]?><br/>
					<?php if ($rSettings["sidebar"]) { echo $_["dashboard_nav_left"]; } else { echo $_["dashboard_nav_top"]; } ?>
				</div>
				<?php } ?>
               
            </div> <!-- end container -->
        </div>
        <!-- end wrapper -->
        <?php if ($rSettings["sidebar"]) { echo "</div>"; } ?>
        <!-- Footer Start -->
        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12 copyright text-center"><?=getFooter()?></div>
                </div>
            </div>
        </footer>
        <!-- end Footer -->

        <script src="assets/js/vendor.min.js"></script>
        <script src="assets/libs/jquery-knob/jquery.knob.min.js"></script>
        <script src="assets/libs/peity/jquery.peity.min.js"></script>
		<script src="assets/libs/apexcharts/apexcharts.min.js"></script>
        <script src="assets/libs/datatables/jquery.dataTables.min.js"></script>
        <script src="assets/libs/jquery-number/jquery.number.js"></script>
        <script src="assets/libs/datatables/dataTables.bootstrap4.js"></script>
        <script src="assets/libs/datatables/dataTables.responsive.min.js"></script>
        <script src="assets/libs/datatables/responsive.bootstrap4.min.js"></script>
        <script src="assets/js/app.min.js"></script>
		<script src="assets/js/jquery.toast.min.js"></script>
		
		<script>
		$(document).on("click",".process-provider",function(event) {		
			var provider_id = $(this).attr("id");							
			event.preventDefault();				
			var target = document.location.href.match(/^([^#]+)/)[1];
			// Request
			var data = {
				provider_id: provider_id					
			};
			
			var myToast = $.toast({
				heading: 'Information',
				text: 'Please wait, processing ...',
				icon: 'info',
				hideAfter: false
			});
			
			$.ajax({
				url: target,
				dataType: 'json',
				type: 'POST',
				data: data,
				success: function(data, textStatus, XMLHttpRequest)
				{
					if (data.valid){		
						myToast.update({
							heading: 'Success',
							text: data.message,
							icon: 'success',
							hideAfter: false
						});
					}else{
						myToast.update({
							heading: 'Error',
							text: data.message || 'An unexpected error occurred, please try again',
							icon: 'error',
							hideAfter: false
						});
					}
				},
				error: function(XMLHttpRequest, textStatus, errorThrown)
				{					
					myToast.update({
						heading: 'Error',
						text: 'Error while contacting server, please try again',
						icon: 'error',
						hideAfter: false
					});					
					
				}
			});			
		});
		</script>
        
        
    </body>
</html>
