import '../support/commands.js';

describe('Author Version - Submit new version', function () {
    let submissionTitle = 'Submission for testing Author Version plugin';
    let firstVersionJustification = 'created to test';
    let secondVersionJustification = 'created to test the plugin';
    let finalVersionJustification = 'Version was created to test the Author Version plugin';

    it('Submit new version to moderators', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionTitle, 'My Submissions as Author');
        cy.get('button:contains("Submit New Version")').click();

        cy.contains('[data-cy="active-modal"] h1', 'Submit New Version').should('be.visible');
        cy.get('label:contains("Justification")');
        cy.contains('Inform moderators and readers of the justification for creating this version. This justification will be made public on the preprint page.');
        cy.saveAuthorVersionJustification(firstVersionJustification);
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', firstVersionJustification);
    });
    it('Moderator views version justification on workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionTitle, 'Submissions with new version');
        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', firstVersionJustification);
    });
    it('Author edits version justification on workflow', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionTitle, 'My Submissions as Author');
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', firstVersionJustification);

        cy.saveAuthorVersionJustification(secondVersionJustification, 'versionJustification');
    });
    it('Moderator edits version justification on workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionTitle, 'Submissions with new version');
        cy.openWorkflowMenu('Title & Abstract');
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', secondVersionJustification);

        cy.saveAuthorVersionJustification(finalVersionJustification, 'versionJustification');
    });
    it('After posting new version - Cant edit justification; Justification shown in landing page', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionTitle, 'Submissions with new version');
        
        cy.openWorkflowMenu('Title & Abstract');
        cy.postAuthorVersion();

        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('not.exist');
        cy.contains(finalVersionJustification);

        cy.window().then(win => {
            const publication = win.pkp.registry.getPiniaStore('workflow').selectedPublication;
            cy.visit(publication.urlPublished);
        });
        cy.get('h2:contains("Version justification")');
        cy.contains(finalVersionJustification);
    });
    it('Checks if version submission works if performed just after version creation', function () {
        cy.login('zwoods', null, 'publicknowledge');
        cy.openAuthorVersionSubmission(submissionTitle, 'My Submissions as Author');
        cy.createAuthorVersion();

        cy.get('button:contains("Submit New Version")').click();
        cy.saveAuthorVersionJustification(finalVersionJustification);
        cy.get('button:contains("Version justification")').click();
        cy.get('input[name="versionJustification"]').should('have.value', finalVersionJustification);
    });
});