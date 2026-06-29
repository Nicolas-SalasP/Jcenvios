const fs   = require('fs');
const path = require('path');

const root      = path.join(__dirname, '..');
const vendorDir = path.join(root, 'public_html/assets/vendor');
const fontsDir  = path.join(vendorDir, 'fonts');

[vendorDir, fontsDir].forEach(d => fs.mkdirSync(d, { recursive: true }));

const copies = [
    ['node_modules/bootstrap/dist/css/bootstrap.min.css',           'public_html/assets/vendor/bootstrap.min.css'],
    ['node_modules/bootstrap-icons/font/bootstrap-icons.min.css',   'public_html/assets/vendor/bootstrap-icons.min.css'],
    ['node_modules/bootstrap-icons/font/fonts/bootstrap-icons.woff2','public_html/assets/vendor/fonts/bootstrap-icons.woff2'],
    ['node_modules/bootstrap-icons/font/fonts/bootstrap-icons.woff', 'public_html/assets/vendor/fonts/bootstrap-icons.woff'],
];

copies.forEach(([src, dest]) => {
    fs.copyFileSync(path.join(root, src), path.join(root, dest));
    console.log('Copied:', dest);
});

console.log('Vendor assets ready.');
