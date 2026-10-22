const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function requests(template, origin = 'https://example.test') {
  const window = {
    location: new URL(origin + '/wp-admin/'),
    albaBoard: {card_details_url: template},
    albaSession: {error: 'Invalid origin'}
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/alba-board-session.js'), 'utf8'), {
    window, document: {addEventListener() {}}, URL, URLSearchParams
  });
  return window.albaBoardRequest;
}

for (const context of ['admin', 'frontend']) {
  for (const [label, template, pathname, route] of [
    ['pretty', 'https://example.test/wp-json/alba-board/v1/card/__ALBA_CARD_ID__', '/wp-json/alba-board/v1/card/23', null],
    ['plain', 'https://example.test/?rest_route=/alba-board/v1/card/__ALBA_CARD_ID__', '/', '/alba-board/v1/card/23'],
    ['index permalinks', 'https://example.test/index.php/wp-json/alba-board/v1/card/__ALBA_CARD_ID__', '/index.php/wp-json/alba-board/v1/card/23', null],
    ['subdirectory plain', 'https://example.test/subsite/?rest_route=%2Falba-board%2Fv1%2Fcard%2F__ALBA_CARD_ID__', '/subsite/', '/alba-board/v1/card/23'],
    ['subdirectory pretty', 'https://example.test/subsite/wp-json/alba-board/v1/card/__ALBA_CARD_ID__', '/subsite/wp-json/alba-board/v1/card/23', null]
  ]) {
    test(`${context}: ${label} preserves the complete WordPress route`, () => {
      const target = new URL(requests(template).cardDetailsUrl(23, context));
      assert.equal(target.pathname, pathname);
      assert.equal(target.searchParams.get('rest_route'), route);
      assert.equal(target.searchParams.get('context'), context);
      assert.equal(target.origin, 'https://example.test');
    });
  }
}

test('preserves other query parameters and replaces an existing context', () => {
  const target = new URL(requests('https://example.test/?locale=es_CO&rest_route=/alba-board/v1/card/__ALBA_CARD_ID__&context=old').cardDetailsUrl(23, 'admin'));
  assert.equal(target.searchParams.get('locale'), 'es_CO');
  assert.equal(target.searchParams.get('rest_route'), '/alba-board/v1/card/23');
  assert.deepEqual(target.searchParams.getAll('context'), ['admin']);
});

test('retains the shared handler’s current-origin HTTPS and port normalization', () => {
  const target = new URL(requests('http://example.test/subsite/?rest_route=/alba-board/v1/card/__ALBA_CARD_ID__', 'https://example.test:8443').cardDetailsUrl(23, 'frontend'));
  assert.equal(target.origin, 'https://example.test:8443');
  assert.equal(target.searchParams.get('rest_route'), '/alba-board/v1/card/23');
});

test('rejects a foreign origin before a card request can send its nonce', () => {
  assert.throws(() => requests('https://other.test/wp-json/alba-board/v1/card/__ALBA_CARD_ID__').cardDetailsUrl(23, 'admin'), /Invalid origin/);
});
