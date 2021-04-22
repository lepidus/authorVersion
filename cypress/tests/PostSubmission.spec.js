

describe('Creates and post a submission', function() {

	var admin = 'admin';
	var adminPassword = 'admin';

    it('Create server', function(){
        cy.login(admin, adminPassword);

		cy.get("body").then($body => {
			if ($body.find('a:contains(' + admin + '):visible').length > 0) {   
				cy.get('a:contains(' + admin + '):visible').click();
				cy.get('a:contains("Dashboard")').click();
				cy.get('.pkpDropdown').click();
				cy.get('a:contains("English")').click();
				cy.get('.app__nav a').contains('Website').click();
				cy.get('.app__page > :nth-child(2) > :nth-child(1) > #setup-button').click();
				cy.get('span[id="cell-en_US-contextPrimary"]').click();
				cy.get('tr[id="component-grid-settings-languages-managelanguagegrid-row-pt_BR"] > :nth-child(5) > :nth-child(1)').click();
				cy.logout();
				//cy.get('tr[id="component-grid-settings-languages-managelanguagegrid-row-en_US"] > :nth-child(5) > :nth-child(1)').click();

			}
			else if($body.find('button[id="myQueue-button"]').length > 0){
				cy.get('.pkpDropdown').click();
				cy.get('a:contains("English")').click();
				cy.get('.app__nav a').contains('Website').click();
				cy.get('.app__page > :nth-child(2) > :nth-child(1) > #setup-button').click();
				cy.get('span[id="cell-en_US-contextPrimary"]').click();
				cy.get('tr[id="component-grid-settings-languages-managelanguagegrid-row-pt_BR"] > :nth-child(5) > :nth-child(1)').click();
				cy.logout();
			}else{
				cy.get('.pkpDropdown').click();
				cy.get('a:contains("English")').click();

				cy.get('div[id=contextGridContainer]').find('a').contains('Create').click();

				// Fill in various details
				cy.wait(2000); // https://github.com/tinymce/tinymce/issues/4355
				cy.get('div[id=editContext]').find('button[label="Português (Brasil)"]').click();
				cy.get('input[name="name-pt_BR"]').type("Author Version Server", {delay: 0});
				cy.get('button').contains('Save').click()
				cy.get('div[id=context-name-error-en_US]').find('span').contains('This field is required.');
				cy.get('div[id=context-acronym-error-en_US]').find('span').contains('This field is required.');
				cy.get('div[id=context-urlPath-error]').find('span').contains('This field is required.');
				cy.get('div[id=context-primaryLocale-error]').find('span').contains('This field is required.');
				cy.get('input[name="name-en_US"]').type("Author Version Server", {delay: 0});
				cy.get('input[name=acronym-en_US]').type('AVS', {delay: 0});
				cy.get('span').contains('Enable this preprint server').siblings('input').check();
				cy.get('input[name="supportedLocales"][value="en_US').check();
				cy.get('input[name="supportedLocales"][value="pt_BR').check();
				cy.get('input[name="primaryLocale"][value="en_US').check();

				// Test invalid path characters
				cy.get('input[name=urlPath]').type('public&-)knowledge', {delay: 0});
				cy.get('button').contains('Save').click()
				cy.get('div[id=context-urlPath-error]').find('span').contains('The path can only include letters');
				cy.get('input[name=urlPath]').clear().type('publicknowledge', {delay: 0});

				// Context descriptions
				cy.setTinyMceContent('context-description-control-en_US', "Server for testing the Author's Version Plugin");
				cy.setTinyMceContent('context-description-control-pt_BR', "Server for testing the Author's Version Plugin");
				cy.get('button').contains('Save').click();

				// Wait for it to finish up before moving on
				cy.contains('Settings Wizard', {timeout: 30000});
				cy.get('button:contains("Languages")').click();
				cy.get('tr[id="component-grid-settings-languages-managelanguagegrid-row-pt_BR"] > :nth-child(5) > :nth-child(1)').click();
				cy.logout();
		
			}
			
		});

    });

    it('Create users', function() {

		cy.login('testmoderator', ' testmoderatortestmoderator');
		cy.get("body").then($body => {
			if ($body.find('.pkp_form_error:contains("Invalid username or password. Please try again."):visible').length > 0) {
				cy.logout();

				cy.login(admin, adminPassword);
				cy.get('a:contains(' + admin + '):visible').click();
				cy.get('a:contains("Dashboard")').click();
				cy.get('a:contains("Users & Roles")').click();
		
				var users = [
					{
						'username': 'testauthor',
						'givenName': 'testauthor',
						'familyName': 'testauthor',
						'country': 'Brasil',
						'affiliation': 'Lepidus',
						'roles': ['Author']
					},
					{
						'username': 'testmoderator',
						'givenName': 'testmoderator',
						'familyName': 'testmoderator',
						'country': 'Brasil',
						'affiliation': 'Lepidus',
						'roles': ['Moderator']
					}
				]
				users.forEach(user => {
					cy.createUser(user);
				});
			}
		});
		
		cy.logout();
    });

    it('Creates a submission', function() {

        data = {
            'title': 'Preprint para teste do Plugin Versão do Autor 2',
			'abstract': 'teste',
			'keywords': [
				'employees',
				'survey'
			]
        };

        cy.login('testauthor', 'testauthortestauthor');
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        // Initialize some data defaults before starting
        if (data.type == 'editedVolume' && !('files' in data)) {
            data.files = [];
            // Edited volumes should default to a single file per chapter, named after it.
            data.chapters.forEach((chapter, index) => {
                data.files.push({
                    'file': 'dummy.pdf',
                    'fileName': chapter.title.substring(0, 40) + '.pdf',
                    'fileTitle': chapter.title,
                    'genre': 'Chapter Manuscript'
                });
                data.chapters[index].files = [chapter.title];
            });
        }
        if (!('files' in data)) data.files = [{
            'file': 'dummy.pdf',
            'fileName': data.title + '.pdf',
            'fileTitle': data.title,
            'genre': Cypress.env('defaultGenre')
        }];
        if (!('keywords' in data)) data.keywords = [];
        if (!('additionalAuthors' in data)) data.additionalAuthors = [];
        if ('series' in data) data.section = data.series; // OMP compatible
        // If 'additionalFiles' is specified, it's to be used to augment the default
        // set, rather than overriding it (as using 'files' would do). Add the arrays.
        if ('additionalFiles' in data) {
            data.files = data.files.concat(data.additionalFiles);
        }
    
        cy.get('a:contains("Make a New Submission"), div#myQueue a:contains("New Submission")').click();
    
        // === Submission Step 1 ===
        if ('section' in data) cy.get('select[id="sectionId"],select[id="seriesId"]').select(data.section);
        cy.get('input[id^="checklist-"]').click({multiple: true});
        switch (data.type) { // Only relevant to OMP
            case 'monograph':
                cy.get('input[id="isEditedVolume-0"]').click();
                break;
            case 'editedVolume':
                cy.get('input[id="isEditedVolume-1"]').click();
                break;
        }
        cy.get('input[id=privacyConsent]').click();
        cy.get('button.submitFormButton').click();
    
        // === Submission Step 2 ===
    
        // OPS uses the galley grid
        if (Cypress.env('contextTitles').en_US == 'Public Knowledge Preprint Server') {
            data.files.forEach(file => {
                cy.get('a:contains("Add galley")').click();
                cy.wait(2000); // Avoid occasional failure due to form init taking time
                cy.get('div.pkp_modal_panel').then($modalDiv => {
                    cy.wait(3000);
                    if ($modalDiv.find('div.header:contains("Create New Galley")').length) {
                        cy.get('div.pkp_modal_panel input[id^="label-"]').type('PDF', {delay: 0});
                        cy.get('div.pkp_modal_panel button:contains("Save")').click();
                        cy.wait(2000); // Avoid occasional failure due to form init taking time
                    }
                });
                cy.get('select[id=genreId]').select(file.genre);
                cy.fixture(file.file, 'base64').then(fileContent => {
                    cy.get('input[type=file]').upload(
                        {fileContent, 'fileName': file.fileName, 'mimeType': 'application/pdf', 'encoding': 'base64'}
                    );
                });
                cy.get('button').contains('Continue').click();
                cy.wait(2000);
                for (const field in file.metadata) {
                    cy.get('input[id^="' + Cypress.$.escapeSelector(field) + '"]:visible,textarea[id^="' + Cypress.$.escapeSelector(field) + '"]').type(file.metadata[field], {delay: 0});
                    cy.get('input[id^="language"').click({force: true}); // Close multilingual and datepicker pop-overs
                }
                cy.get('button').contains('Continue').click();
                cy.get('button').contains('Complete').click();
            });
    
        // Other applications use the submission files list panel
        } else {
            cy.get('button:contains("Add File")');
    
            // A callback function used to prevent Cypress from failing
            // when an uncaught exception occurs in the code. This is a
            // workaround for an exception that is thrown when a file's
            // genre is selected in the modal form. This exception happens
            // because the submission step 2 form handler attaches a
            // validator to the modal form.
            //
            // It should be possible to remove this workaround once the
            // submission process has been fully ported to Vue.
            const allowException = function(error, runnable) {
                return false;
            }
            cy.on('uncaught:exception', allowException);
    
            // File uploads
            const primaryFileGenres = ['Article Text', 'Book Manuscript', 'Chapter Manuscript'];
            data.files.forEach(file => {
                cy.fixture(file.file, 'base64').then(fileContent => {
                    cy.get('input[type=file]').upload(
                        {fileContent, 'fileName': file.fileName, 'mimeType': 'application/pdf', 'encoding': 'base64'}
                    );
                    var $row = cy.get('a:contains("' + file.fileName + '")').parents('.listPanel__item');
                    if (primaryFileGenres.includes(file.genre)) {
                        // For some reason this is locating two references to the button,
                        // so just click the last one, which should be the most recently
                        // uploaded file.
                        $row.get('button:contains("' + file.genre + '")').last().click();
                        $row.get('span:contains("' + file.genre + '")');
                    } else {
                        $row.get('button:contains("Other")').last().click();
                        cy.get('#submission-files-container .modal label:contains("' + file.genre + '")').click();
                        cy.get('#submission-files-container .modal button:contains("Save")').click();
                    }
                    // Make sure the genre selection is complete before moving to the
                    // next file.
                    $row.get('button:contains("What kind of file is this?")').should('not.exist');
                });
            });
        }
    
        // Save the ID to the data object
        cy.location('search')
            .then(search => {
                // this.submission.id = parseInt(search.split('=')[1], 10);
                data.id = parseInt(search.split('=')[1], 10);
            });
    
        cy.get('button').contains('Save and continue').click();
    
        // === Submission Step 3 ===
        // Metadata fields
        cy.get('input[id^="title-en_US-"').type(data.title, {delay: 0});
        cy.get('label').contains('Title').click(); // Close multilingual popover
        cy.get('textarea[id^="abstract-en_US"]').then(node => {
            cy.setTinyMceContent(node.attr('id'), data.abstract);
        });
        cy.get('ul[id^="en_US-keywords-"]').then(node => {
            data.keywords.forEach(keyword => {
                node.tagit('createTag', keyword);
            });
        });
        data.additionalAuthors.forEach(author => {
            if (!('role' in author)) author.role = 'Author';
            cy.get('a[id^="component-grid-users-author-authorgrid-addAuthor-button-"]').click();
            cy.wait(250);
            cy.get('input[id^="givenName-en_US-"]').type(author.givenName, {delay: 0});
            cy.get('input[id^="familyName-en_US-"]').type(author.familyName, {delay: 0});
            cy.get('select[id=country]').select(author.country);
            cy.get('input[id^="email"]').type(author.email, {delay: 0});
            if ('affiliation' in author) cy.get('input[id^="affiliation-en_US-"]').type(author.affiliation, {delay: 0});
            cy.get('label').contains(author.role).click();
            cy.get('form#editAuthor').find('button:contains("Save")').click();
            cy.get('div[id^="component-grid-users-author-authorgrid-"] span.label:contains("' + Cypress.$.escapeSelector(author.givenName + ' ' + author.familyName) + '")');
        });
        // Chapters (OMP only)
        if ('chapters' in data) data.chapters.forEach(chapter => {
            cy.waitJQuery();
            cy.get('a[id^="component-grid-users-chapter-chaptergrid-addChapter-button-"]:visible').click();
            cy.wait(2000); // Avoid occasional failure due to form init taking time
    
            // Contributors
            chapter.contributors.forEach(contributor => {
                cy.get('form[id="editChapterForm"] label:contains("' + Cypress.$.escapeSelector(contributor) + '")').click();
            });
    
            // Title/subtitle
            cy.get('form[id="editChapterForm"] input[id^="title-en_US-"]').type(chapter.title, {delay: 0});
            if ('subtitle' in chapter) {
                cy.get('form[id="editChapterForm"] input[id^="subtitle-en_US-"]').type(chapter.subtitle, {delay: 0});
            }
            cy.get('div.pkp_modal_panel div:contains("Add Chapter")').click(); // FIXME: Resolve focus problem on title field
    
            cy.flushNotifications();
            cy.get('form[id="editChapterForm"] button:contains("Save")').click();
            cy.get('div:contains("Your changes have been saved.")');
            cy.waitJQuery();
    
            // Files
            if ('files' in chapter) {
                cy.get('div[id="chaptersGridContainer"] a:contains("' + Cypress.$.escapeSelector(chapter.title) + '")').click();
                chapter.files.forEach(file => {
                    cy.get('form[id="editChapterForm"] label:contains("' + Cypress.$.escapeSelector(chapter.title.substring(0, 40)) + '")').click();
                });
                cy.flushNotifications();
                cy.get('form[id="editChapterForm"] button:contains("Save")').click();
                cy.get('div:contains("Your changes have been saved.")');
            }
    
            cy.get('div[id^="component-grid-users-chapter-chaptergrid-"] a.pkp_linkaction_editChapter:contains("' + Cypress.$.escapeSelector(chapter.title) + '")');
        });
        cy.waitJQuery();
        cy.get('form[id=submitStep3Form]').find('button').contains('Save and continue').click();
    
        // === Submission Step 4 ===
        cy.waitJQuery();
        cy.get('form[id=submitStep4Form]').find('button').contains('Finish Submission').click();
        cy.get('button.pkpModalConfirmButton').click();
        cy.waitJQuery();
        cy.get('h2:contains("Submission complete")');
        cy.logout();
    });

    it('Activate Plugin', function() {

        cy.login(admin, adminPassword);
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        cy.get('.app__nav a').contains('Website').click();
        cy.get('button[id="plugins-button"]').click();
		cy.get("body").then($body => {
			if (!($body.find('tr[id="component-grid-settings-plugins-settingsplugingrid-category-generic-row-authorversionplugin"] > :nth-child(3) > :nth-child(1) > :checked').length > 0)) {
				cy.get('#component-grid-settings-plugins-settingsplugingrid-category-generic-row-authorversionplugin > :nth-child(3) >').click();
				cy.get('div:contains(\'The plugin "Author Version Plugin" has been enabled.\')');
			}
		});
        cy.logout();
    });

    it('Assign Moderator', function() {

        cy.login(admin, adminPassword);
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        cy.get('#myQueue > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(1)').contains('View').click();
        cy.get('a:contains("Assign")').click();
        cy.get('select[name=filterUserGroupId]').select('Moderator');
        cy.get('button:contains("Search")').click();
        cy.get('tr:contains("testmoderator testmoderator") > .first_column > .advancedUserSelect').click();
        cy.get('button:contains("OK")').click();
		cy.wait(1500);
        cy.logout();
    });
    
    it('Post Submission', function() {

        cy.login('testmoderator', 'testmoderatortestmoderator');
        cy.get('#pkpDropdown1').click();
        cy.get('.profile.show > .dropdown-menu > :nth-child(1) > a').click();
        cy.get('a:contains("Submissions")').click();
        cy.get('#myQueue > .submissionsListPanel > .listPanel > .listPanel__body > .listPanel__items > .listPanel__itemsList > :nth-child(1)').contains('View').click();
        cy.get('li > .pkpButton').click();
        cy.get('.pkpPublication > .pkpHeader > .pkpHeader__actions > .pkpButton').click();
        cy.get('.pkp_modal > .pkp_modal_panel > .content > .pkpWorkflow__publishModal > .pkpForm > .pkpFormPages > .pkpFormPage > .pkpFormPage__footer > .pkpFormPage__buttons > .pkpButton').click();
        cy.get('.app__main').contains('Posted');
        cy.logout();
    });
    
});
