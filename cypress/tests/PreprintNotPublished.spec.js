

describe('Check active features', function() {
    it('Change relation to not submitted and verify preprint not submitted as author', function() {

        cy.login('testauthor', 'testauthortestauthor');
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(1) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpButton').click();
        cy.get('input[name="relationStatus"][value=1]').click();
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpDropdown__content').contains('Save').click();
        cy.wait(1500);
        cy.reload();
        cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton');
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpButton');
        cy.logout();
    });

    it('Verify preprint not submitted as moderator', function() {

        cy.login('testmoderator', 'testmoderatortestmoderator');
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        cy.get('a:contains("Submissions")').click();
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(1) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton');
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpButton')
        cy.logout();
    });

    it('Change relation to submitted and verify preprint submitted as author', function() {

        cy.login('testauthor', 'testauthortestauthor');
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        cy.get('#archive-button').click();
        cy.get('#archive > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(1) > .listPanel__item--submission > .listPanel__itemSummary > .listPanel__itemActions > .pkpButton').click();
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpButton').click();
        cy.get('input[name="relationStatus"][value=2]').click();
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpDropdown__content').contains('Save').click();
        cy.wait(1500);
        cy.reload();
        cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton');
        cy.get('.pkpPublication__relation > .pkpDropdown > .pkpButton');
        cy.logout();
    });

    it('Verify preprint submitted as moderator', function() {

        cy.login('testmoderator', 'testmoderatortestmoderator');
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