// @ts-check
const { themes: prismThemes } = require('prism-react-renderer');

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'Laravel Shaka Packager',
  tagline: 'A Laravel package to interact with Shaka Packager for media packaging',

  url: 'https://foxws.github.io',
  baseUrl: '/laravel-shaka/',

  organizationName: 'foxws',
  projectName: 'laravel-shaka',
  deploymentBranch: 'gh-pages',
  trailingSlash: false,

  onBrokenLinks: 'throw',
  onBrokenMarkdownLinks: 'warn',

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          path: '../docs',
          routeBasePath: '/',
          sidebarPath: require.resolve('./sidebars.js'),
          editUrl: 'https://github.com/foxws/laravel-shaka/edit/main/docs/',
        },
        blog: false,
        theme: {
          customCss: require.resolve('./src/css/custom.css'),
        },
      }),
    ],
  ],

  themes: [
    [
      '@easyops-cn/docusaurus-search-local',
      /** @type {import('@easyops-cn/docusaurus-search-local').PluginOptions} */
      ({
        hashed: true,
        indexBlog: false,
        docsRouteBasePath: '/',
      }),
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      navbar: {
        title: 'Laravel Shaka Packager',
        items: [
          {
            href: 'https://github.com/foxws/laravel-shaka',
            label: 'GitHub',
            position: 'right',
          },
          {
            href: 'https://packagist.org/packages/foxws/laravel-shaka',
            label: 'Packagist',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Docs',
            items: [
              { label: 'Introduction', to: '/' },
              { label: 'Installation', to: '/installation' },
              { label: 'Quick Reference', to: '/quick-reference' },
            ],
          },
          {
            title: 'More',
            items: [
              { label: 'GitHub', href: 'https://github.com/foxws/laravel-shaka' },
              { label: 'Packagist', href: 'https://packagist.org/packages/foxws/laravel-shaka' },
            ],
          },
        ],
        copyright: `Copyright © ${new Date().getFullYear()} foxws. Built with Docusaurus.`,
      },
      prism: {
        theme: prismThemes.github,
        darkTheme: prismThemes.dracula,
      },
    }),
};

module.exports = config;
