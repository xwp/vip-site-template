# Newspack Blocks

This plugin is meant to serve as a container for most Newspack Gutenberg blocks. There may be certain blocks that relate to specific functionality in other plugins, in which case they would live with the primary functionality, but besides this exception most will live in this one.

## Setup

To get set up for block development, run `composer install && npm install`

### Generating Builds

To generate a build of the current blocks, run `npm run build`.

To clean out the built blocks, run `npm run clean`.

### Developing

To work on Block development and have Webpack watch your files for changes run: `npm start`.

#### Linting

is performed on changed files before commiting. In other words, is run during `pre-commit` git hook, but only on staged files. The hook is configured in `composer.json`.

|              | PHP                                                   | JS                            | SCSS                               |
| :----------- | :---------------------------------------------------- | :---------------------------- | :--------------------------------- |
| tool         | [PHPCS](https://github.com/squizlabs/PHP_CodeSniffer) | [eslint](https://eslint.org/) | [stylelint](https://stylelint.io/) |
| config       | `.phpcs.xml.dist`                                     | `.eslintrc.js`                | `.stylelintrc`                     |
| run manually | `composer lint`                                       | `npm run lint:js`             | `npm run lint:scss`                |
| autofix ✨   | `./vendor/bin/phpcbf <file>`                          | `npm run lint:js -- --fix`    | `npm run lint:scss -- --fix`       |

### Building New Blocks

To get started with a new block:

- Duplicate one of the example block directories in `src/`
- Rename the directory to the slug of your block.
- At minimum edit `index.js` and change name and title definitions.
- Add the block slug to the `production` array in `src/setup/blocks.json`
- If the block requires server-side code add the slug to the `$newspack_blocks_blocks` array in `newspack-blocks.php`
- Execute `npm run build`. If all went smoothly, you should see a Newspack category in the block picker, and your block should appear within it.

### Usage

[End-user documentation can be found here](https://help.newspack.com/publishing-and-appearance/blocks/homepage-posts).
