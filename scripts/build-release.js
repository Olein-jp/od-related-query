const { spawnSync } = require( 'child_process' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );

const AdmZip = require( 'adm-zip' );
const ignore = require( 'ignore' );

const projectRoot = path.resolve( __dirname, '..' );
const packageData = require( path.join( projectRoot, 'package.json' ) );
const pluginSlug = packageData.name;
const pluginFile = path.join( projectRoot, `${ pluginSlug }.php` );
const outputFile = path.join( projectRoot, `${ pluginSlug }.zip` );

/**
 * Runs a command and stops when it fails.
 *
 * @param {string}            command Command name.
 * @param {string[]}          args    Command arguments.
 * @param {string}            cwd     Command working directory.
 * @param {NodeJS.ProcessEnv} env     Command environment.
 */
function run( command, args, cwd, env = process.env ) {
	const result = spawnSync( command, args, {
		cwd,
		env,
		stdio: 'inherit',
	} );

	if ( result.error ) {
		throw result.error;
	}

	if ( result.status !== 0 ) {
		throw new Error( `${ command } exited with code ${ result.status }.` );
	}
}

/**
 * Removes a leading "v" from a release version.
 *
 * @param {string} version Version or tag.
 * @return {string} Normalized version.
 */
function normalizeVersion( version ) {
	return version.trim().replace( /^v/, '' );
}

/**
 * Reads and validates every release version source.
 */
function validateVersions() {
	const pluginSource = fs.readFileSync( pluginFile, 'utf8' );
	const headerMatch = pluginSource.match(
		/^[ \t*#@/]*Version:\s*([^\s]+)/im
	);
	const constantMatch = pluginSource.match(
		/OD_RELATED_QUERY_VERSION',\s*'([^']+)'/
	);
	const expectedVersion = normalizeVersion(
		process.env.RELEASE_VERSION ||
			process.env.GITHUB_REF_NAME ||
			packageData.version
	);
	const versions = {
		'package.json': packageData.version,
		'plugin header': headerMatch?.[ 1 ],
		'plugin constant': constantMatch?.[ 1 ],
		'release tag': expectedVersion,
	};

	for ( const [ source, version ] of Object.entries( versions ) ) {
		if ( ! version ) {
			throw new Error( `Could not read the version from ${ source }.` );
		}

		if ( normalizeVersion( version ) !== expectedVersion ) {
			throw new Error(
				`Version mismatch: ${ source } is ${ version }, expected ${ expectedVersion }.`
			);
		}
	}

	process.stdout.write( `Release version: ${ expectedVersion }\n` );
}

/**
 * Copies distributable repository files to a staging directory.
 *
 * @param {string} stagingDirectory Destination directory.
 */
function copyDistributionFiles( stagingDirectory ) {
	const rules = fs.readFileSync(
		path.join( projectRoot, '.distignore' ),
		'utf8'
	);
	const matcher = ignore().add( rules );
	const allowedRoots = new Set( [
		'LICENSE',
		'README.md',
		'build',
		'languages',
		`${ pluginSlug }.php`,
		'src',
	] );

	/**
	 * Recursively copies one directory.
	 *
	 * @param {string} sourceDirectory Source directory.
	 */
	function copyDirectory( sourceDirectory ) {
		for ( const entry of fs.readdirSync( sourceDirectory, {
			withFileTypes: true,
		} ) ) {
			const source = path.join( sourceDirectory, entry.name );
			const relativePath = path
				.relative( projectRoot, source )
				.split( path.sep )
				.join( '/' );
			const ignoredPath = entry.isDirectory()
				? `${ relativePath }/`
				: relativePath;
			const rootPath = relativePath.split( '/' )[ 0 ];

			if (
				! allowedRoots.has( rootPath ) ||
				relativePath === 'vendor' ||
				relativePath.startsWith( 'vendor/' ) ||
				matcher.ignores( ignoredPath )
			) {
				continue;
			}

			const destination = path.join( stagingDirectory, relativePath );

			if ( entry.isDirectory() ) {
				fs.mkdirSync( destination, { recursive: true } );
				copyDirectory( source );
			} else if ( entry.isFile() ) {
				fs.mkdirSync( path.dirname( destination ), {
					recursive: true,
				} );
				fs.copyFileSync( source, destination );
			}
		}
	}

	copyDirectory( projectRoot );
}

/**
 * Installs only Composer production dependencies into staging.
 *
 * @param {string} temporaryDirectory Temporary workspace.
 * @param {string} stagingDirectory   Distribution staging directory.
 */
function installProductionDependencies( temporaryDirectory, stagingDirectory ) {
	const composerDirectory = path.join(
		temporaryDirectory,
		'composer-source'
	);
	fs.mkdirSync( composerDirectory, { recursive: true } );

	for ( const filename of [ 'composer.json', 'composer.lock' ] ) {
		fs.copyFileSync(
			path.join( projectRoot, filename ),
			path.join( composerDirectory, filename )
		);
	}

	run(
		'composer',
		[
			'install',
			'--no-dev',
			'--classmap-authoritative',
			'--no-interaction',
			'--no-progress',
		],
		composerDirectory,
		{
			...process.env,
			COMPOSER_CACHE_DIR: path.join(
				temporaryDirectory,
				'composer-cache'
			),
		}
	);

	fs.cpSync(
		path.join( composerDirectory, 'vendor' ),
		path.join( stagingDirectory, 'vendor' ),
		{ recursive: true }
	);
}

/**
 * Ensures the staged plugin contains runtime files and no development tools.
 *
 * @param {string} stagingDirectory Distribution staging directory.
 */
function validateDistribution( stagingDirectory ) {
	const requiredFiles = [
		`${ pluginSlug }.php`,
		'build/index.js',
		'src/class-plugin.php',
		'vendor/autoload.php',
	];
	const forbiddenPaths = [
		'composer.json',
		'composer.lock',
		'node_modules',
		'package.json',
		'scripts',
		'tests',
		'vendor/bin/phpunit',
	];

	for ( const filename of requiredFiles ) {
		if ( ! fs.existsSync( path.join( stagingDirectory, filename ) ) ) {
			throw new Error(
				`Required release file is missing: ${ filename }`
			);
		}
	}

	for ( const filename of forbiddenPaths ) {
		if ( fs.existsSync( path.join( stagingDirectory, filename ) ) ) {
			throw new Error(
				`Development file was included in the release: ${ filename }`
			);
		}
	}

	const sourceFiles = fs.readdirSync( path.join( stagingDirectory, 'src' ) );
	if ( sourceFiles.some( ( filename ) => filename.endsWith( '.js' ) ) ) {
		throw new Error( 'Development JavaScript was included in src/.' );
	}
}

let temporaryDirectory;

try {
	validateVersions();
	temporaryDirectory = fs.mkdtempSync(
		path.join( os.tmpdir(), `${ pluginSlug }-release-` )
	);
	const stagingDirectory = path.join( temporaryDirectory, pluginSlug );
	fs.mkdirSync( stagingDirectory, { recursive: true } );

	copyDistributionFiles( stagingDirectory );
	installProductionDependencies( temporaryDirectory, stagingDirectory );
	validateDistribution( stagingDirectory );

	const archive = new AdmZip();
	archive.addLocalFolder( stagingDirectory, pluginSlug );
	archive.writeZip( outputFile );

	process.stdout.write( `Created ${ outputFile }\n` );
} finally {
	if ( temporaryDirectory ) {
		fs.rmSync( temporaryDirectory, {
			force: true,
			recursive: true,
		} );
	}
}
