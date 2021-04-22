

describe('Check inactive features', function() {
    

    it('Change relation to published and verify preprint published as author', function() {

        cy.login('author', 'authorauthor' );
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(1) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpButton').click();
        cy.get('input[name="relationStatus"][value=3]').click();
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpDropdown__content').contains('Save').click();
        cy.wait(1500);
        cy.reload();
        cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton').should('not.exist');
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpButton').should('not.exist');
        cy.logout();
    });

    it('Verify preprint published as moderator', function() {

        cy.login('moderator', 'moderatormoderator' );
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        cy.get('a:contains("Submissions")').click();
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(1) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton');
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpButton')
        cy.logout();
    });
});