const fs = require( 'node:fs' );
const path = require( 'node:path' );

const languagesDirectory = path.resolve( __dirname, '..', 'languages' );
const generatedJsonPattern = /^od-related-query-ja-[a-f0-9]{32}\.json$/;

if ( ! fs.existsSync( languagesDirectory ) ) {
	process.exit( 0 );
}

for ( const fileName of fs.readdirSync( languagesDirectory ) ) {
	if ( generatedJsonPattern.test( fileName ) ) {
		fs.unlinkSync( path.join( languagesDirectory, fileName ) );
	}
}
