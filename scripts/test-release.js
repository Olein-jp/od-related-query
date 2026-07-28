const { spawnSync } = require( 'child_process' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );

const AdmZip = require( 'adm-zip' );

const projectRoot = path.resolve( __dirname, '..' );
const packageData = require( path.join( projectRoot, 'package.json' ) );
const pluginSlug = packageData.name;
const archiveFile = path.join( projectRoot, `${ pluginSlug }.zip` );
const wpEnv = path.join( projectRoot, 'node_modules', '.bin', 'wp-env' );

/**
 * Runs a command and stops when it fails.
 *
 * @param {string}   command Command name.
 * @param {string[]} args    Command arguments.
 */
function run( command, args ) {
	const result = spawnSync( command, args, {
		cwd: projectRoot,
		env: process.env,
		stdio: 'inherit',
	} );

	if ( result.error ) {
		throw result.error;
	}

	if ( result.status !== 0 ) {
		throw new Error( `${ command } exited with code ${ result.status }.` );
	}
}

if ( ! fs.existsSync( archiveFile ) ) {
	throw new Error(
		`${ archiveFile } does not exist. Run npm run release:build first.`
	);
}

const archive = new AdmZip( archiveFile );
const archiveEntries = archive.getEntries().map( ( entry ) => entry.entryName );

if (
	! archiveEntries.includes( `${ pluginSlug }/${ pluginSlug }.php` ) ||
	! archiveEntries.includes( `${ pluginSlug }/vendor/autoload.php` )
) {
	throw new Error( 'The release ZIP is missing required runtime files.' );
}

if (
	archiveEntries.some(
		( filename ) =>
			filename.includes( '/node_modules/' ) ||
			filename.includes( '/tests/' ) ||
			filename.includes( '/scripts/' ) ||
			/\/src\/.*\.js$/.test( filename )
	)
) {
	throw new Error( 'The release ZIP contains development files.' );
}

const temporaryDirectory = fs.mkdtempSync(
	path.join( os.tmpdir(), `${ pluginSlug }-activation-` )
);
const pluginDirectory = path.join( temporaryDirectory, pluginSlug );
const configFile = path.join( temporaryDirectory, 'wp-env.json' );
let environmentStarted = false;

try {
	archive.extractAllTo( temporaryDirectory, true );
	fs.writeFileSync(
		configFile,
		JSON.stringify(
			{
				plugins: [ pluginDirectory ],
				testsEnvironment: false,
			},
			null,
			2
		)
	);

	environmentStarted = true;
	run( wpEnv, [ 'start', `--config=${ configFile }`, '--auto-port' ] );
	run( wpEnv, [
		'run',
		'cli',
		`--config=${ configFile }`,
		'wp',
		'plugin',
		'deactivate',
		pluginSlug,
	] );
	run( wpEnv, [
		'run',
		'cli',
		`--config=${ configFile }`,
		'wp',
		'plugin',
		'activate',
		pluginSlug,
	] );
	run( wpEnv, [
		'run',
		'cli',
		`--config=${ configFile }`,
		'wp',
		'plugin',
		'is-active',
		pluginSlug,
	] );

	process.stdout.write(
		'Release ZIP activated successfully in an isolated WordPress environment.\n'
	);
} finally {
	try {
		if ( environmentStarted ) {
			run( wpEnv, [ 'stop', `--config=${ configFile }` ] );
		}
	} finally {
		fs.rmSync( temporaryDirectory, {
			force: true,
			recursive: true,
		} );
	}
}
