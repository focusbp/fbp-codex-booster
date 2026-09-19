<?php

// 0 = responsive (including legacy settings without this field), 1 = desktop.
function fbp_normalize_standard_screen_responsive($value): int {
	return in_array($value, [1, "1"], true) ? 1 : 0;
}
