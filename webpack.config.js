const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/app.js')
    .enableSingleRuntimeChunk()
    .enableSassLoader()
    //.enablePostCssLoader()
    .enableVersioning(false)
;

module.exports = Encore.getWebpackConfig();
