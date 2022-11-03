module.exports = {
	// Use a callback to avoid passing the changed file paths to the script.
	'composer.*': () => 'npm run lint-composer',
	'*.php': () => 'npm run lint-php',
}
