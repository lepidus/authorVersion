describe('Author Version - Submit new version', function () {
    let versionJustification = 'This version was created to test the Author Version plugin';

    it('Button to submit new version to moderators', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.get('#archive-button').click();
        cy.contains('View Woods').click({force: true});
        cy.get('button:contains("Submit New Version")').click();

        cy.get('h2:contains("Submit New Version")');
        cy.get('label:contains("Justification")');
        cy.contains('Inform the justification for creating this version');
        cy.get('input[name="versionJustification"]').clear().type(versionJustification, {delay: 0});
        cy.get('div[modalname="submitVersion"] button:contains("Save")').click();
    });
    it('Moderator views version justification on workflow', function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Woods');
        cy.get('#publication-button').click();
        cy.contains('View version justification').click();
        cy.contains(versionJustification);
    });
    it('Version justification displayed in preprint landing page', function () {
        cy.findSubmissionAsEditor('dbarnes', null, 'Woods');
        
        cy.get('.pkpHeader .pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();

        cy.get('.pkpHeader__actions button:contains("View")').click();

        cy.get('h2:contains("Version justification")');
        cy.contains(versionJustification);
    })
});