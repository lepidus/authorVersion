describe('Author Version - Submit new version', function () {
    let versionJustification = 'This version was created to test the Author Version plugin';

    it('Submit new version to moderators', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Submit New Version")').click();

        cy.get('h2:contains("Submit New Version")');
        cy.get('label:contains("Justification")');
        cy.contains('Inform the justification for creating this version');
        cy.get('input[name="versionJustification"]').clear().type(versionJustification, {delay: 0});
        cy.get('div[modalname="submitVersion"] button:contains("Submit")').click();
    });
    it('Moderator views version justification on workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('#publication-button').click();
        cy.get('button:contains("Version justification")').click();
        cy.contains(versionJustification);
    });
    it('Version justification is displayed in preprint landing page', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Woods').click({force: true});
        
        cy.get('#publication-button').click();
        cy.get('.pkpHeader .pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();

        cy.get('.pkpHeader__actions a:contains("View")').click();

        cy.get('h2:contains("Version justification")');
        cy.contains(versionJustification);
    })
    it('Checks if version submission works if performed just after version creation', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Create New Version")').click();
        cy.wait(2000);

        cy.get('button:contains("Submit New Version")').click();
        cy.get('input[name="versionJustification"]').clear().type(versionJustification, {delay: 0});
        cy.get('div[modalname="submitVersion"] button:contains("Submit")').click();
        
        cy.get('button:contains("Version justification")').click();
        cy.contains(versionJustification);
    });
});