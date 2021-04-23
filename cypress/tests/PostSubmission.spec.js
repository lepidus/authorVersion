

describe('Creates and post a submission', function() {

	var admin = 'admin';
	var adminPassword = 'admin';

    it('Change language to English', function(){
        cy.login(admin, adminPassword);
        cy.visit('/index/admin/settings#setup/languages');
        cy.get('input[id="select-cell-en_US-sitePrimary"]').click();
        cy.get("body").then($body => {
			if ($body.find('.ok:visible').length > 0) {   
                cy.get('.ok').click();
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
                cy.get('a:contains("Dashboard"):visible').click();
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
        cy.get('a:contains("testauthor"):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
        
        if (data.type == 'editedVolume' && !('files' in data)) {
            data.files = [];
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
        if ('series' in data) data.section = data.series; 
        
        
        if ('additionalFiles' in data) {
            data.files = data.files.concat(data.additionalFiles);
        }
    
        cy.get('a:contains("Make a New Submission"), div#myQueue a:contains("New Submission")').click();
        
        if ('section' in data) cy.get('select[id="sectionId"],select[id="seriesId"]').select(data.section);
        cy.get('input[id^="checklist-"]').click({multiple: true});
        switch (data.type) { 
            case 'monograph':
                cy.get('input[id="isEditedVolume-0"]').click();
                break;
            case 'editedVolume':
                cy.get('input[id="isEditedVolume-1"]').click();
                break;
        }
        cy.get('input[id=privacyConsent]').click();
        cy.get('button.submitFormButton').click();
    
        if (Cypress.env('contextTitles').en_US == 'Public Knowledge Preprint Server') {
            data.files.forEach(file => {
                cy.get('a:contains("Add galley")').click();
                cy.wait(2000); 
                cy.get('div.pkp_modal_panel').then($modalDiv => {
                    cy.wait(3000);
                    if ($modalDiv.find('div.header:contains("Create New Galley")').length) {
                        cy.get('div.pkp_modal_panel input[id^="label-"]').type('PDF', {delay: 0});
                        cy.get('div.pkp_modal_panel button:contains("Save")').click();
                        cy.wait(2000); 
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
                    cy.get('input[id^="language"').click({force: true}); 
                }
                cy.get('button').contains('Continue').click();
                cy.get('button').contains('Complete').click();
            });
        } else {
            cy.get('button:contains("Add File")');
            const allowException = function(error, runnable) {
                return false;
            }
            cy.on('uncaught:exception', allowException);
            const primaryFileGenres = ['Article Text', 'Book Manuscript', 'Chapter Manuscript'];
            data.files.forEach(file => {
                cy.fixture(file.file, 'base64').then(fileContent => {
                    cy.get('input[type=file]').upload(
                        {fileContent, 'fileName': file.fileName, 'mimeType': 'application/pdf', 'encoding': 'base64'}
                    );
                    var $row = cy.get('a:contains("' + file.fileName + '")').parents('.listPanel__item');
                    if (primaryFileGenres.includes(file.genre)) {
                        $row.get('button:contains("' + file.genre + '")').last().click();
                        $row.get('span:contains("' + file.genre + '")');
                    } else {
                        $row.get('button:contains("Other")').last().click();
                        cy.get('#submission-files-container .modal label:contains("' + file.genre + '")').click();
                        cy.get('#submission-files-container .modal button:contains("Save")').click();
                    }
                    $row.get('button:contains("What kind of file is this?")').should('not.exist');
                });
            });
        }
        cy.location('search')
            .then(search => {
                data.id = parseInt(search.split('=')[1], 10);
            });
    
        cy.get('button').contains('Save and continue').click();
        cy.get('input[id^="title-en_US-"').type(data.title, {delay: 0});
        cy.get('label').contains('Title').click(); 
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
        
        if ('chapters' in data) data.chapters.forEach(chapter => {
            cy.waitJQuery();
            cy.get('a[id^="component-grid-users-chapter-chaptergrid-addChapter-button-"]:visible').click();
            cy.wait(2000); 
    
            
            chapter.contributors.forEach(contributor => {
                cy.get('form[id="editChapterForm"] label:contains("' + Cypress.$.escapeSelector(contributor) + '")').click();
            });
    
            
            cy.get('form[id="editChapterForm"] input[id^="title-en_US-"]').type(chapter.title, {delay: 0});
            if ('subtitle' in chapter) {
                cy.get('form[id="editChapterForm"] input[id^="subtitle-en_US-"]').type(chapter.subtitle, {delay: 0});
            }
            cy.get('div.pkp_modal_panel div:contains("Add Chapter")').click(); 
    
            cy.flushNotifications();
            cy.get('form[id="editChapterForm"] button:contains("Save")').click();
            cy.get('div:contains("Your changes have been saved.")');
            cy.waitJQuery();
    
            
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
    
        
        cy.waitJQuery();
        cy.get('form[id=submitStep4Form]').find('button').contains('Finish Submission').click();
        cy.get('button.pkpModalConfirmButton').click();
        cy.waitJQuery();
        cy.get('h2:contains("Submission complete")');
        cy.logout();
    });

    it('Activate Plugin', function() {

        cy.login(admin, adminPassword);
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
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
        cy.get('a:contains(' + admin + '):visible').click();
        cy.get('a:contains("Dashboard"):visible').click();
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
