/*!
 * Minimal browser global for React's jsx-runtime used by @xyflow/react UMD.
 */
(function (global) {
	if (!global.React || global.jsxRuntime) {
		return;
	}

	function jsx(type, config, maybeKey) {
		var props = {};
		var key = maybeKey === undefined ? null : "" + maybeKey;

		if (config) {
			for (var propName in config) {
				if (!Object.prototype.hasOwnProperty.call(config, propName)) {
					continue;
				}
				if (propName === "key") {
					if (key === null) {
						key = "" + config[propName];
					}
					continue;
				}
				props[propName] = config[propName];
			}
		}

		if (key !== null) {
			props.key = key;
		}

		return global.React.createElement(type, props);
	}

	global.jsxRuntime = {
		Fragment: global.React.Fragment,
		jsx: jsx,
		jsxs: jsx
	};
})(typeof globalThis !== "undefined" ? globalThis : this);
