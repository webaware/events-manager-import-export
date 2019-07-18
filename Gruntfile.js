module.exports = function (grunt) {

	grunt.initConfig({
		pkg: grunt.file.readJSON("package.json"),

		clean: [ "dist/**" ],

		copy: {
			main: {
				files: [
					{
						src: [
							"./**",
							"!./node_modules/**",
							"!./vendor/**",
							"!./composer.*",
							"!./Gruntfile.js",
							"!./package*.json",
							"!./phpcs*.xml",
						],
						dest: "dist/<%= pkg.name %>/"
					}
				]
			}
		},

		compress: {
			options: {
				archive: "./dist/<%= pkg.name %>-<%= pkg.version %>.zip",
				mode: "zip"
			},
			all: {
				files: [{
					expand: true,
					cwd: "./dist/",
					date: new Date(),
					src: [ "<%= pkg.name %>/**" ]
				}]
			}
		},

		makepot: {
			// @link https://github.com/cedaro/grunt-wp-i18n/blob/develop/docs/makepot.md
			target: {
				options: {
					type: "wp-plugin",
					domainPath: "languages/",
					exclude: [
						"dist/.*",
						"lib/.*",
						"node_modules/.*"
					],
					potHeaders: {
						poedit: true,
						"x-poedit-keywordslist": true,
						"x-poedit-sourcecharset": "UTF-8",
						"x-translation-home": "<%= pkg.translator.url %>",
						"Report-Msgid-Bugs-To":	"<%= pkg.translator.email %>",
						"Last-Translator": "<%= pkg.translator.name %> <<%= pkg.translator.email %>>",
						"Language-Team": "<%= pkg.translator.name %> <<%= pkg.translator.email %>>"
					}
				}
			}
		}

	});

	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks("grunt-contrib-compress");
	grunt.loadNpmTasks("grunt-contrib-copy");
	grunt.loadNpmTasks("grunt-wp-i18n");

	grunt.registerTask("release", ["clean","copy","compress"]);

};
