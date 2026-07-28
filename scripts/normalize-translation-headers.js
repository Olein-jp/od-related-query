const fs = require( 'node:fs' );
const path = require( 'node:path' );

const translationFile = path.resolve(
	__dirname,
	'..',
	'languages',
	'od-related-query-ja.po'
);
const contents = fs.readFileSync( translationFile, 'utf8' );
const normalizedContents = contents.replace(
	/^"POT-Creation-Date: .*\\n"$/m,
	'"POT-Creation-Date: \\n"'
);

fs.writeFileSync( translationFile, normalizedContents );
