describe('Author Version - Plugin setup', function () {
    it('Enables Author Version plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('a:contains("Website")').click();

		cy.waitJQuery();
		cy.get('button#plugins-button').click();

		cy.get('input[id^=select-cell-authorversionplugin]').check();
		cy.get('input[id^=select-cell-authorversionplugin]').should('be.checked');
    });
});