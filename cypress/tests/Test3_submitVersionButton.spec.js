describe('Author Version - Submit new version', function () {
    it('Button to submit new version to moderators', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Submit New Version")').click();

        cy.get('h2:contains("Submit New Version")');
        cy.get('label:contains("Justification")');
        cy.contains('Inform the justification for creating this version');
    });
});