<?php
/*
Plugin Name: HeadStart editor guard (local)
Description: LOCAL ONLY, never sent to live. Weekly-Update--Divi-Gatekeeper calls dispatch('core/edit-post').removeEditorPanel() before that store exists on this WordPress version, which leaves a blank editor. This sends that one call to core/editor when the store is empty. Don't fix it in the snippet's working copy: a sync with live would undo that. See work/local-editor-crash/plan.md.
*/

add_action(
	'admin_head',
	function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}
		?>
<script id="hs-local-editor-guard">
(function () {
	if (!window.wp || !wp.data || typeof wp.data.dispatch !== 'function') {
		return;
	}
	var realDispatch = wp.data.dispatch;
	function guardedDispatch(store) {
		var actions = realDispatch.apply(this, arguments);
		if (actions || store !== 'core/edit-post') {
			return actions;
		}
		return {
			removeEditorPanel: function (panel) {
				var editor = realDispatch('core/editor');
				return editor && editor.removeEditorPanel ? editor.removeEditorPanel(panel) : undefined;
			}
		};
	}
	// Bundled packages can expose read-only exports, so if a plain assignment doesn't take, layer over wp.data.
	try {
		wp.data.dispatch = guardedDispatch;
	} catch (e) {}
	if (wp.data.dispatch !== guardedDispatch) {
		wp.data = Object.create(wp.data, { dispatch: { value: guardedDispatch } });
	}
	if (wp.data.dispatch !== guardedDispatch) {
		console.warn('hs-local-editor-guard: could not wrap wp.data.dispatch');
	}
})();
</script>
		<?php
	},
	1
);
