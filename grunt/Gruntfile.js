module.exports = function(grunt) {

	// ==== SETTINGS START =============================================================================================

	var root = '../',
		cssRoot = root + 'css/',
		lessRoot = root + 'less/',
		jsRoot = root + 'javascript/',

		prefixedCssDest = cssRoot + '.prefixed/',

		jsSrc = jsRoot + 'src/',
		concatedJs = jsSrc + 'concat/',

		cssFileName1 = 'i19n';

	// ==== SETTINGS END ===============================================================================================


	var files = {
		less1: {},
		autoprefix: {},
		mincss: {},
		stripmq: {},
		uglifyFunctions: {},
		concatFunctions: {}
	};

	files.less1[prefixedCssDest + cssFileName1 + '.css'] = lessRoot + cssFileName1 + '.less';

	files.autoprefix[cssRoot + cssFileName1 + '.css'] = prefixedCssDest + cssFileName1 + '.css';

	files.mincss[cssRoot + cssFileName1 + '.min.css'] = cssRoot + cssFileName1 + '.css';

	files.stripmq[cssRoot + cssFileName1 + '-ie.css'] = cssRoot + cssFileName1 + '.css';

	files.uglifyFunctions[jsRoot + 'i19n.min.js'] = [
		jsSrc + '**/*.js',
		'!' + concatedJs + '**/*.*'
	];
	files.concatFunctions[concatedJs + 'i19n.js'] = [
		jsSrc + '**/*.js',
		'!' + concatedJs + '**/*.*'
	];

	grunt.initConfig({

		pkg: grunt.file.readJSON('package.json'),
		less: {
			options: {
				paths: [cssRoot],
				strictImports : true,
				sourceMap: true,
			},
			style: {
				options: {
					sourceMapFilename: prefixedCssDest + cssFileName1 + '.css.map',
					sourceMapURL: cssFileName1 + '.css.map',
					sourceMapRootpath: '../../less/',	// path relative to compiled .css - css is in css/.prefixed/ -> ../../
					sourceMapBasepath: lessRoot		// path relative to Gruntfile.js
				},
				files: files.less1
			},
		},
		autoprefixer: {
			all: {
				options: {
					map:true,
					browsers:['last 3 versions', 'ie > 8', 'safari > 7']
				},
				files: files.autoprefix
			}
		},
		cssmin: {
			options: {
				mergeIntoShorthands: false,
				sourceMap: false,
				roundingPrecision: -1
			},
			all: {
				files: [{
					expand: true,
					cwd: cssRoot,
					// src: [cssFileName1 + '.css', cssFileName2 + '.css'],
					src: [cssFileName1 + '.css'],
					dest: cssRoot,
					ext: '.min.css'
				}]
			}
		},
		stripmq: {
			options: {
				width: 1024,
				type: 'screen'
			},
			all: {
				files: files.stripmq
			}
		},
		uglify: {
			functions:{
				files: files.uglifyFunctions
			}
		},
		concat: {
			functions:{
				files: files.concatFunctions
			}
		},
		watch: {
			styles: {
				files: [lessRoot + '**/*.less'],
				tasks: [
					'css'
				],
				options: {
					nospawn: true
				}
			},
			functions: {
				files: [
					'!' + concatedJs + '**/*.js',
					jsSrc + '**/*.js',
				],
				tasks:[
					'js'
				],
				options: {
					nospawn: true
				}
			}
		},
		concurrent: {
			options: {
				logConcurrentOutput: true
			},
			all:{
				tasks:[
					'watch:styles',
					'watch:functions',
					'update-caniuse'
				]
			},
			styles: {
				tasks: [
					'watch:styles',
					'update-caniuse'
				]
			},
			functions: {
				tasks: [
					'watch:functions'
				]
			}
		}

	});

	grunt.loadNpmTasks('grunt-contrib-less');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-concurrent');
	grunt.loadNpmTasks('grunt-autoprefixer');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	// grunt.loadNpmTasks('grunt-stripmq');

	grunt.registerTask('hidria-dev', [
		'css',
		'js',
			'concurrent:all'
	]);

	grunt.registerTask('default', [
		'hidria-dev'
	]);

	grunt.registerTask('js', [
		'concat:functions',
			'uglify:functions'
	]);
	grunt.registerTask('js-dev', [
		'js',
			'concurrent:functions'
	]);

	grunt.registerTask('css', [
		'less:style',
			'autoprefixer:all',
				'cssmin:all',
					// 'stripmq:all'

	]);
	grunt.registerTask('css-dev', [
		'css',
			'concurrent:styles'
	]);

	grunt.registerTask('update-caniuse', 'update caniuse database for autoprefixer', function(){
		var exec = require('child_process').exec,
			cb = this.async();

		exec('npm update caniuse-db', {cwd:'./'}, function(err, stdout, stderr){
			console.log('updataing autoprefixer "can i use" db ', err, stdout, stderr);
			cb();
		});

	});

};