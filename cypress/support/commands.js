function beginSubmission(submissionData) {
    cy.get('input[name="locale"][value="en"]').click();
    cy.setTinyMceContent('startSubmission-title-control', submissionData.title);
    
    cy.get('input[name="submissionRequirements"]').check();
    cy.get('input[name="privacyConsent"]').check();
    cy.contains('button', 'Begin Submission').click();
}

function detailsStep(submissionData) {
    cy.setTinyMceContent('titleAbstract-abstract-control-en', submissionData.abstract);
    submissionData.keywords.forEach(keyword => {
        cy.get('#titleAbstract-keywords-control-en').type(keyword, {delay: 0});
        cy.get('#titleAbstract-keywords-control-en').type('{enter}', {delay: 0});
    });
    cy.contains('button', 'Continue').click();
}

function filesStep(submissionData) {
    cy.addSubmissionGalleys(submissionData.files);
    cy.contains('button', 'Continue').click();
}

Cypress.Commands.add('createSubmission', function(submissionData) {
	cy.get('div#myQueue a:contains("New Submission")').click();

    beginSubmission(submissionData);
    detailsStep(submissionData);
    filesStep(submissionData);
    cy.contains('button', 'Continue').click();
    cy.contains('button', 'Continue').click();
    cy.contains('button', 'Submit').click();
    cy.get('.modal__panel:visible').within(() => {
        cy.contains('button', 'Submit').click();
    });

    cy.waitJQuery();
    cy.contains('h1', 'Submission complete');
});