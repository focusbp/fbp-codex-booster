
<script src="js/jquery-3.6.4.min.js"></script>
<script src="js/jquery-ui.min.js"></script>
<script src="js/chart.min.js"></script>
<script src="js/js.cookie.js"></script>
<script src="js/player.js"></script>
<script src="js/react.production.min.js"></script>
<script src="js/react-dom.production.min.js"></script>
<script src="js/react-jsx-runtime-shim.js"></script>
<script src="js/reactflow.min.js"></script>

<!-- SQUARE -->
{if $testserver }
	<script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
{else}
	<script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>
{/if}

<!-- google map -->
{if $setting.api_key_map != ""}
	<script>
		status_map=0;
		function initMap(){
			status_map=1;
		}
	</script>
	<script src="https://maps.googleapis.com/maps/api/js?key={$setting.api_key_map}&libraries=geometry,places&callback=initMap&loading=async"></script>
{/if}
