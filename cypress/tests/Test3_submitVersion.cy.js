describe('Author Version - Submit new version', function () {
    let firstVersionJustification = 'created to test';
    let secondVersionJustification = 'created to test the plugin';
    let finalVersionJustification = 'Version was created to test the Author Version plugin';

    it('Submit new version to moderators', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Submit New Version")').click();

        cy.get('h2:contains("Submit New Version")');
        cy.get('label:contains("Justification")');
        cy.contains('Inform moderators and readers of the justification for creating this version. This justification will be made public on the preprint page.');
        cy.get('input[name="versionJustification"]').clear().type(firstVersionJustification, {delay: 0});
        cy.get('form button:contains("Submit")').click();
    });
    it('Moderator views version justification on workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('#publication-button').click();
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', firstVersionJustification);
    });
    it('Author edits version justification on workflow', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', firstVersionJustification);

        cy.get('input[name="versionJustification"]').clear().type(secondVersionJustification, {delay: 0});
        cy.get('.pkpWorkflow__versionJustificationForm button:contains("Save")').click();
    });
    it('Moderator edits version justification on workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('#publication-button').click();
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', secondVersionJustification);

        cy.get('input[name="versionJustification"]').clear().type(finalVersionJustification, {delay: 0});
        cy.get('.pkpWorkflow__versionJustificationForm button:contains("Save")').click();
    });
    it('After posting new version - Cant edit justification; Justification shown in landing page', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#newVersion-button').click();
        cy.contains('View Woods').click({force: true});
        
        cy.get('#publication-button').click();
        cy.get('.pkpHeader .pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.reload();

        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('not.exist');
        cy.contains(finalVersionJustification);

        cy.get('.pkpHeader__actions a:contains("View")').click();
        cy.get('h2:contains("Version justification")');
        cy.contains(finalVersionJustification);
    });
    it('Checks if version submission works if performed just after version creation', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Create New Version")').click();
        cy.wait(2000);

        cy.get('button:contains("Submit New Version")').click();
        cy.get('input[name="versionJustification"]').clear().type(finalVersionJustification, {delay: 0});
        cy.get('form button:contains("Submit")').click();
        
        cy.waitJQuery();
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', finalVersionJustification);
    });
});