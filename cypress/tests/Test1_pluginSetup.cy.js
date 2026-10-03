import '../support/commands.js';

describe('Author Version - Plugin setup', function () {
    it('Enables Author Version plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('nav').contains('Settings').click();
        cy.get('nav').contains('Website').click({force: true});
		cy.get('button#plugins-button').click();

		cy.get('input[id^=select-cell-authorversionplugin]').check();
		cy.get('input[id^=select-cell-authorversionplugin]').should('be.checked');
    });
});