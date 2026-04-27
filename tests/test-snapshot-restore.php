<?php
/**
 * Unit Tests: Plugin Snapshot & Restore
 *
 * Validates the collateral deactivation prevention logic.
 * Run from the command line: php tests/test-snapshot-restore.php
 *
 * This file is intentionally excluded from WordPress's autoloader.
 * It stubs WordPress functions locally for isolated unit testing.
 */

// This file is a CLI test runner, not loaded by WordPress.
// The ABSPATH check below prevents direct web access while allowing CLI execution.
if ( defined( 'ABSPATH' ) && isset( $_SERVER['REQUEST_METHOD'] ) ) {
    exit; // Block web requests; allow CLI and WordPress-context execution.
}

// ── Lightweight WordPress function stubs ──────────────────────────────────────
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- test stubs intentionally shadow WP core functions for isolation.
$wpmm_test_options      = [];
$wpmm_test_site_options = [];
$wpmm_test_transients   = [];

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $key, $default = false ) {
        global $wpmm_test_options;
        return $wpmm_test_options[ $key ] ?? $default;
    }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $key, $value, $autoload = null ) {
        global $wpmm_test_options;
        $wpmm_test_options[ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'get_site_option' ) ) {
    function get_site_option( $key, $default = false ) {
        global $wpmm_test_site_options;
        return $wpmm_test_site_options[ $key ] ?? $default;
    }
}
if ( ! function_exists( 'update_site_option' ) ) {
    function update_site_option( $key, $value ) {
        global $wpmm_test_site_options;
        $wpmm_test_site_options[ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        global $wpmm_test_transients;
        return $wpmm_test_transients[ $key ] ?? false;
    }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $ttl = 0 ) {
        global $wpmm_test_transients;
        $wpmm_test_transients[ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'is_multisite' ) ) {
    function is_multisite() { return true; }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $v ) { return false; }
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals

// ── Snapshot/restore logic (mirrored from includes/ajax.php) ─────────────────
function wpmm_test_run_snapshot_and_restore( $session_id, $slug, $upgrade_fn ) {
    $snapshot_key = 'wpmm_active_snapshot_' . md5( $session_id ?: 'default' );
    $snapshot     = get_transient( $snapshot_key );
    if ( false === $snapshot ) {
        $snapshot = [
            'active_plugins'          => (array) get_option( 'active_plugins', [] ),
            'active_sitewide_plugins' => is_multisite()
                ? (array) get_site_option( 'active_sitewide_plugins', [] )
                : [],
        ];
        set_transient( $snapshot_key, $snapshot, 1800 );
    }
    $active_before   = $snapshot['active_plugins'];
    $sitewide_before = $snapshot['active_sitewide_plugins'];

    $upgrade_fn();

    $wpmm_test_restored = [];
    $active_after       = (array) get_option( 'active_plugins', [] );
    $sitewide_after     = is_multisite()
        ? (array) get_site_option( 'active_sitewide_plugins', [] )
        : [];

    $deactivated = array_diff( $active_before, $active_after );
    $to_restore  = array_values( array_filter( $deactivated, function( $p ) use ( $slug ) {
        return $p !== $slug;
    } ) );
    if ( ! empty( $to_restore ) ) {
        $new_active = array_unique( array_merge( $active_after, $to_restore ) );
        sort( $new_active );
        update_option( 'active_plugins', $new_active );
        $wpmm_test_restored = array_merge( $wpmm_test_restored, $to_restore );
    } else {
        $new_active = $active_after;
    }

    if ( is_multisite() && ! empty( $sitewide_before ) ) {
        $deactivated_keys = array_diff_key( $sitewide_before, $sitewide_after );
        unset( $deactivated_keys[ $slug ] );
        if ( ! empty( $deactivated_keys ) ) {
            update_site_option( 'active_sitewide_plugins', array_merge( $sitewide_after, $deactivated_keys ) );
            $wpmm_test_restored = array_merge( $wpmm_test_restored, array_keys( $deactivated_keys ) );
            $new_sitewide = array_merge( $sitewide_after, $deactivated_keys );
        } else {
            $new_sitewide = $sitewide_after;
        }
    } else {
        $new_sitewide = $sitewide_after;
    }

    if ( ! empty( $wpmm_test_restored ) ) {
        set_transient( $snapshot_key, [
            'active_plugins'          => $new_active,
            'active_sitewide_plugins' => $new_sitewide,
        ], 1800 );
    }

    return $wpmm_test_restored;
}

// ── Test runner ───────────────────────────────────────────────────────────────
$wpmm_test_passed = 0;
$wpmm_test_failed = 0;

function wpmm_test_assert( $name, $condition, $detail = '' ) {
    global $wpmm_test_passed, $wpmm_test_failed;
    if ( $condition ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output only, no HTML context.
        echo "  â  {$name}\n";
        $wpmm_test_passed++;
    } else {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output only, no HTML context.
        echo "  â  {$name}" . ( $detail ? " -- {$detail}" : '' ) . "\n";
        $wpmm_test_failed++;
    }
}

function wpmm_test_reset() {
    global $wpmm_test_options, $wpmm_test_site_options, $wpmm_test_transients;
    $wpmm_test_options      = [];
    $wpmm_test_site_options = [];
    $wpmm_test_transients   = [];
}

// ── Tests ─────────────────────────────────────────────────────────────────────
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo "\nGreenskeeper -- Plugin Snapshot & Restore Tests\n";
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo str_repeat( '-', 52 ) . "\n\n";

// Test 1: Site-level collateral deactivation
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo "Test 1: Site-level collateral deactivation\n";
wpmm_test_reset();
update_option( 'active_plugins', [
    'wp-optimize/wp-optimize.php',
    'sucuri-scanner/sucuri.php',
    'google-site-kit/google-site-kit.php',
    'microsoft-clarity/microsoft-clarity.php',
] );
update_site_option( 'active_sitewide_plugins', [] );
$wpmm_t1_slug     = 'wp-optimize/wp-optimize.php';
$wpmm_t1_restored = wpmm_test_run_snapshot_and_restore( 'test-1', $wpmm_t1_slug, function() {
    update_option( 'active_plugins', [] );
} );
$wpmm_t1_after = get_option( 'active_plugins', [] );
wpmm_test_assert( 'sucuri restored',          in_array( 'sucuri-scanner/sucuri.php', $wpmm_t1_after, true ) );
wpmm_test_assert( 'google-site-kit restored', in_array( 'google-site-kit/google-site-kit.php', $wpmm_t1_after, true ) );
wpmm_test_assert( 'clarity restored',         in_array( 'microsoft-clarity/microsoft-clarity.php', $wpmm_t1_after, true ) );
wpmm_test_assert( 'failed plugin NOT restored', ! in_array( $wpmm_t1_slug, $wpmm_t1_after, true ) );
wpmm_test_assert( '3 items restored', count( $wpmm_t1_restored ) === 3, 'got ' . count( $wpmm_t1_restored ) );

// Test 2: Network-activated plugin deactivation
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo "\nTest 2: Network-activated (sitewide) collateral deactivation\n";
wpmm_test_reset();
update_option( 'active_plugins', [ 'wp-optimize/wp-optimize.php' ] );
update_site_option( 'active_sitewide_plugins', [
    'sucuri-scanner/sucuri.php'               => 1,
    'google-site-kit/google-site-kit.php'     => 1,
    'microsoft-clarity/microsoft-clarity.php' => 1,
] );
$wpmm_t2_slug     = 'wp-optimize/wp-optimize.php';
$wpmm_t2_restored = wpmm_test_run_snapshot_and_restore( 'test-2', $wpmm_t2_slug, function() {
    update_option( 'active_plugins', [] );
    update_site_option( 'active_sitewide_plugins', [] );
} );
$wpmm_t2_sitewide = get_site_option( 'active_sitewide_plugins', [] );
wpmm_test_assert( 'sucuri in sitewide',   isset( $wpmm_t2_sitewide['sucuri-scanner/sucuri.php'] ) );
wpmm_test_assert( 'site-kit in sitewide', isset( $wpmm_t2_sitewide['google-site-kit/google-site-kit.php'] ) );
wpmm_test_assert( 'clarity in sitewide',  isset( $wpmm_t2_sitewide['microsoft-clarity/microsoft-clarity.php'] ) );
wpmm_test_assert( '3 network plugins',    count( $wpmm_t2_restored ) === 3, 'got ' . count( $wpmm_t2_restored ) );

// Test 3: Updated plugin not restored
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo "\nTest 3: Updated plugin excluded from restore\n";
wpmm_test_reset();
update_option( 'active_plugins', [ 'akismet/akismet.php', 'the-target/plugin.php' ] );
update_site_option( 'active_sitewide_plugins', [] );
$wpmm_t3_slug     = 'the-target/plugin.php';
$wpmm_t3_restored = wpmm_test_run_snapshot_and_restore( 'test-3', $wpmm_t3_slug, function() {
    update_option( 'active_plugins', [ 'akismet/akismet.php' ] );
} );
$wpmm_t3_after = get_option( 'active_plugins', [] );
wpmm_test_assert( 'target NOT restored', ! in_array( $wpmm_t3_slug, $wpmm_t3_after, true ) );
wpmm_test_assert( 'akismet unchanged',   in_array( 'akismet/akismet.php', $wpmm_t3_after, true ) );
wpmm_test_assert( 'restored list empty', count( $wpmm_t3_restored ) === 0, 'got ' . count( $wpmm_t3_restored ) );

// Test 4: Retry uses original pre-batch snapshot
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo "\nTest 4: Retry restores from original pre-batch snapshot\n";
wpmm_test_reset();
update_option( 'active_plugins', [
    'wp-optimize/wp-optimize.php',
    'divi-machine/divi-machine.php',
    'sucuri-scanner/sucuri.php',
] );
update_site_option( 'active_sitewide_plugins', [] );
$wpmm_t4_slug = 'wp-optimize/wp-optimize.php';
wpmm_test_run_snapshot_and_restore( 'test-4', $wpmm_t4_slug, function() {
    update_option( 'active_plugins', [ 'wp-optimize/wp-optimize.php', 'sucuri-scanner/sucuri.php' ] );
} );
update_option( 'active_plugins', [ 'wp-optimize/wp-optimize.php' ] );
$wpmm_t4_restored = wpmm_test_run_snapshot_and_restore( 'test-4', $wpmm_t4_slug, function() {
    update_option( 'active_plugins', [] );
} );
$wpmm_t4_after = get_option( 'active_plugins', [] );
wpmm_test_assert( 'divi restored on retry',   in_array( 'divi-machine/divi-machine.php', $wpmm_t4_after, true ) );
wpmm_test_assert( 'sucuri restored on retry',  in_array( 'sucuri-scanner/sucuri.php', $wpmm_t4_after, true ) );
wpmm_test_assert( 'wp-optimize NOT restored', ! in_array( 'wp-optimize/wp-optimize.php', $wpmm_t4_after, true ) );

// Test 5: Clean update
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo "\nTest 5: Clean update -- no spurious restoration\n";
wpmm_test_reset();
update_option( 'active_plugins', [ 'akismet/akismet.php', 'hello-dolly/hello.php' ] );
update_site_option( 'active_sitewide_plugins', [] );
$wpmm_t5_slug     = 'hello-dolly/hello.php';
$wpmm_t5_restored = wpmm_test_run_snapshot_and_restore( 'test-5', $wpmm_t5_slug, function() {
    update_option( 'active_plugins', [ 'akismet/akismet.php' ] );
} );
wpmm_test_assert( 'nothing spuriously restored', count( $wpmm_t5_restored ) === 0, 'got ' . count( $wpmm_t5_restored ) );
wpmm_test_assert( 'akismet untouched', in_array( 'akismet/akismet.php', get_option( 'active_plugins', [] ), true ) );

// ── Summary ───────────────────────────────────────────────────────────────────
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo "\n" . str_repeat( '-', 52 ) . "\n";
$wpmm_test_total = $wpmm_test_passed + $wpmm_test_failed;
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI only.
echo "Results: {$wpmm_test_passed}/{$wpmm_test_total} passed" .
    ( $wpmm_test_failed > 0 ? " -- {$wpmm_test_failed} FAILED" : ' -- ALL PASSED' ) . "\n\n";
exit( $wpmm_test_failed > 0 ? 1 : 0 );
