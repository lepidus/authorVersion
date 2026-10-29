Cypress.config('defaultCommandTimeout', 15000);

function beginSubmission(submissionData) {
    cy.contains('label', 'English').click();
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
    cy.addAuthorVersionGalleys(submissionData.files);
    cy.contains('button', 'Continue').click();
}

Cypress.Commands.add('addAuthorVersionGalleys', (files) => {
    files.forEach(file => {
        cy.intercept('POST', /\/(update-galley|updateGalley)(?:[/?]|$)/).as('saveAuthorVersionGalley');
        cy.intercept('POST', /\/(upload-file|uploadFile)(?:[/?]|$)/).as('uploadAuthorVersionFile');
        cy.intercept('GET', /\/(edit-metadata|editMetadata)(?:[/?]|$)/).as('loadAuthorVersionFileMetadata');
        cy.intercept('POST', /\/(save-metadata|saveMetadata)(?:[/?]|$)/).as('saveAuthorVersionFileMetadata');
        cy.intercept('GET', /\/(finish-file-submission|finishFileSubmission)(?:[/?]|$)/).as('finishAuthorVersionFile');

        cy.contains('a', 'Add File').click();
        cy.get('[role="dialog"]:visible input[id^="label-"]')
            .should('be.visible').clear().type(file.galleyLabel || 'PDF', {delay: 0});
        cy.contains('[role="dialog"]:visible button', 'Save').click();
        cy.wait('@saveAuthorVersionGalley').its('response.statusCode').should('eq', 200);

        cy.get('[id^="fileUploadWizard"]:visible select#genreId')
            .should('be.visible').select(file.genre);
        cy.fixture(file.file, 'base64').then(fileContent => {
            cy.get('[id^="fileUploadWizard"]:visible input[type="file"]').attachFile({
                fileContent,
                filePath: file.fileName,
                mimeType: file.mimeType,
                encoding: 'base64',
            });
        });
        cy.wait('@uploadAuthorVersionFile').its('response.statusCode').should('eq', 200);
        cy.get('[id^="fileUploadWizard"]:visible + #wizardButtons #continueButton:visible')
            .should('be.enabled').click();
        cy.wait('@loadAuthorVersionFileMetadata').its('response.statusCode').should('eq', 200);

        cy.get('[id^="metadataForm"]:visible input[id^="name-"]:visible')
            .first().clear().type(file.fileName, {delay: 0}).blur();
        Object.entries(file.metadata || {}).forEach(([field, value]) => {
            const id = Cypress.$.escapeSelector(field);
            cy.get(`[id^="metadataForm"]:visible input[id^="${id}"]:visible, [id^="metadataForm"]:visible textarea[id^="${id}"]:visible`)
                .first().clear().type(value, {delay: 0}).blur();
        });
        cy.get('[id^="fileUploadWizard"]:visible + #wizardButtons #continueButton:visible')
            .should('be.enabled').click();
        cy.wait('@saveAuthorVersionFileMetadata').its('response.statusCode').should('eq', 200);
        cy.wait('@finishAuthorVersionFile').its('response.statusCode').should('eq', 200);
        cy.contains('[id^="fileUploadWizard"]:visible + #wizardButtons #continueButton:visible', 'Complete')
            .should('be.enabled').click();
        cy.get('[id^="fileUploadWizard"]:visible').should('not.exist');
    });
});

Cypress.Commands.add('createSubmission', function(submissionData) {
	cy.contains('a', 'New Submission').click();

    beginSubmission(submissionData);
    detailsStep(submissionData);
    filesStep(submissionData);
    cy.contains('button', 'Continue').click();
    cy.contains('button', 'Continue').click();
    cy.intercept('POST', '**/submissions/*/submit').as('submitAuthorVersionPreprint');
    cy.contains('button', 'Submit').click();
    cy.get('[data-cy="dialog"]').within(() => {
        cy.contains('button', 'Submit').click();
    });

    cy.wait('@submitAuthorVersionPreprint').its('response.statusCode').should('eq', 200);
    cy.contains('h1', 'Submission complete');
});

Cypress.Commands.add('openAuthorVersionSubmission', (title, view = 'My Submissions as Author') => {
    cy.contains('nav a', view).click();
    if (view === 'My Submissions as Author') {
        cy.request({
            url: 'index.php/publicknowledge/api/v1/_submissions/assigned',
            qs: {searchPhrase: title, 'assignedWithRoles[]': 65536},
        }).then(response => {
            expect(response.status).to.equal(200);
            expect(response.body.items).to.have.length(1);
            const submissionId = response.body.items[0].id;
            cy.url().then(currentUrl => {
                const workflowUrl = new URL(currentUrl);
                workflowUrl.searchParams.set('workflowSubmissionId', submissionId);
                workflowUrl.searchParams.set('workflowMenuKey', 'publication_titleAbstract');
                cy.visit(workflowUrl.toString());
            });
        });
    } else {
        cy.openSubmission(title);
    }
    cy.get('[data-cy="active-modal"]').should('be.visible');
    cy.openWorkflowMenu('Title & Abstract');
});

Cypress.Commands.add('createAuthorVersion', () => {
    cy.intercept('POST', '**/publications/*/version').as('createAuthorVersion');
    cy.contains('[data-cy="active-modal"] button', 'Create New Version').click();
    cy.contains('[data-cy="dialog"] button', 'Yes').click();
    cy.wait('@createAuthorVersion').its('response.statusCode').should('eq', 200);
    cy.contains('[data-cy="active-modal"] button', 'Submit New Version').should('be.visible');
});

Cypress.Commands.add('postAuthorVersion', () => {
    cy.intercept('POST', '**/publications/*/publish').as('postAuthorVersion');
    cy.contains('[data-cy="active-modal"] button', /^\s*Post\s*$/).click();
    cy.contains('[id^="publish"] button', /^\s*Post\s*$/).click();
    cy.wait('@postAuthorVersion').its('response.statusCode').should('eq', 200);
    cy.contains('[data-cy="active-modal"] button', 'Unpost').should('be.visible');
});

Cypress.Commands.add('unpostAuthorVersion', () => {
    cy.intercept('POST', '**/publications/*/unpublish').as('unpostAuthorVersion');
    cy.contains('[data-cy="active-modal"] button', 'Unpost').click();
    cy.contains('[data-cy="dialog"] button', 'Unpost').click();
    cy.wait('@unpostAuthorVersion').its('response.statusCode').should('eq', 200);
    cy.contains('[data-cy="active-modal"] button', /^\s*Post\s*$/).should('be.visible');
});

Cypress.Commands.add('saveAuthorVersionJustification', (justification, operation = 'submitVersion') => {
    cy.intercept('POST', `**/authorVersion/${operation}*`).as('saveAuthorVersionJustification');
    cy.get('[data-cy="active-modal"] input[name="versionJustification"]')
        .clear().type(justification, {delay: 0});
    cy.contains('[data-cy="active-modal"] form button', operation === 'submitVersion' ? 'Submit' : 'Save').click();
    cy.wait('@saveAuthorVersionJustification').its('response.statusCode').should('eq', 200);
    cy.contains('[data-cy="active-modal"] button', 'Version justification').should('be.visible');
});
