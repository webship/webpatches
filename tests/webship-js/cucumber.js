module.exports = {
  default: {
    timeout: 30000,
    requireModule: ['tsx/cjs'],
    require: [
      'node_modules/webship-js/tests/step-definitions/**/*.js',          // Webship-js core step definitions (auto HTML report on exit; disable: WEBSHIP_REPORT_DISABLE=1).
      'node_modules/@webship-js/drupal-core-steps/tests/step-definitions/**/*.js', // @webship-js/drupal-core-steps: login by handle, the testing users, named pages, messages, listings, the editor, the media library, access control, site structure.
      // 'node_modules/webship-js/tests/step-definitions-diffy/**/*.js', // Diffy step definitions (optional).
      'tests/step-definitions/**/*.js',                                  // Your custom step definitions.
    ],
    paths: ['tests/features/**/*.feature'],
    format: [
      '@cucumber/pretty-formatter',
      'json:tests/reports/cucumber_report.json',
    ],
    formatOptions: {
      colorsEnabled: true,
      theme: {
        'feature keyword': ['bold', 'blue'],
        'feature name': ['blue', 'underline'],
        'feature description': ['blueBright'],
        'scenario keyword': ['bold', 'magenta'],
        'scenario name': ['magenta', 'underline'],
        'step keyword': ['bold', 'green'],
        'step text': ['greenBright', 'italic'],
      },
    },
    worldParameters: {
      launchUrl: process.env.LAUNCH_URL || 'https://webshipprep.ddev.site',
      // Accounts the login by handle (@webship-js/drupal-core-steps) logs in as; `Given I add the
      // testing users` creates the ones that are missing, with their roles. Webmaster is the
      // site-install super-admin (drush site:install --account-name=webmaster --account-pass=...).
      users: {
        'Webmaster': { username: 'webmaster', email: 'webmaster@example.test', password: process.env.DRUPAL_ADMIN_PASSWORD || 'dD.123123ddd', isAdmin: true },
        'Content editor': { username: 'content_editor_user', email: 'content_editor_user@example.test', password: 'dD.123123ddd', roles: ['content_editor'] },
        'Authenticated user': { username: 'authenticated_user', email: 'authenticated_user@example.test', password: 'dD.123123ddd', roles: [] },
      },
      minWaitTime: {
        page: 3000,
        before_scenario: 0,
        after_scenario: 0,
        before_step: 0,
        after_step: 0,
      },
      selectors: {
        css: {},
        xpath: {},
        filesPath: './tests/selectors/',
        files: [],
        offset: 60,
        breakpoints: {
          xs:   { width: 375,  height: 667  },
          sm:   { width: 576,  height: 800  },
          md:   { width: 768,  height: 1024 },
          lg:   { width: 992,  height: 768  },
          xl:   { width: 1200, height: 900, default: true },
          xxl:  { width: 1400, height: 900  },
          xxxl: { width: 1920, height: 1080 },
        },
      },
      screenshot: {
        dir: './screenshots',
        purge: false,
        onFailed: true,
        onEveryStep: false,
        alwaysFullscreen: false,
        failedPrefix: 'failed_',
        filenamePattern: '{datetime}.{feature_file}.feature_{step_line}.{ext}',
        filenamePatternFailed: '{failed_prefix}{datetime}.{feature_file}.feature_{step_line}.{ext}',
        infoTypes: '',
      },
      // Diffy visual-regression (optional). Uncomment and set apiKey / projectId to enable.
      // diffy: {
      //   apiKey: '',                                 // DIFFY_API_KEY
      //   projectId: 0,                               // DIFFY_PROJECT_ID
      //   breakpoints: '640,1200',                    // DIFFY_BREAKPOINTS
      //   windowHeight: 2000,                         // DIFFY_WINDOW_HEIGHT
      //   screenshotsDir: '',                         // DIFFY_SCREENSHOTS_DIR
      //   baseUrl: 'https://app.diffy.website/api/',  // DIFFY_API_BASE_URL
      //   maxWait: 30,                                // DIFFY_MAX_WAIT (seconds)
      //   env1Url: '',                                // DIFFY_ENV1_URL
      //   env2Url: '',                                // DIFFY_ENV2_URL
      // },
    },
  },
};
