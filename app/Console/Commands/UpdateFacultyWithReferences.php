<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateFacultyWithReferences extends Command
{
    protected $signature = 'faculty:update-references';

    protected $description = 'Update faculty seeders to include References section in last chapters';

    public function handle()
    {
        $this->info('This command will show you what needs to be updated in the faculty structures.');
        $this->info('The References section should be added as the last section of the final chapter.');

        $this->newLine();
        $this->info('Faculty Structure Updates Needed:');
        $this->newLine();

        // Example for Science Faculty
        $this->info('1. SCIENCE FACULTY:');
        $this->info('   Last Chapter: Chapter 5 - "Summary, Conclusion, and Recommendations"');
        $this->info('   Add Section: 5.4 - References');
        $this->info('   Word Count: N/A (references don\'t have word count)');
        $this->info('   Citation Style: APA or Harvard');
        $this->newLine();

        // Example for Engineering Faculty
        $this->info('2. ENGINEERING FACULTY:');
        $this->info('   Last Chapter: Chapter 6 - "Summary, Conclusion, and Recommendations"');
        $this->info('   Add Section: 6.4 - References');
        $this->info('   Word Count: N/A');
        $this->info('   Citation Style: IEEE or APA');
        $this->newLine();

        // Example for Social Sciences Faculty
        $this->info('3. SOCIAL SCIENCES FACULTY:');
        $this->info('   Last Chapter: Chapter 5 - "Summary, Conclusion, and Recommendations"');
        $this->info('   Add Section: 5.5 - References (after "Suggestions for Further Research")');
        $this->info('   Word Count: N/A');
        $this->info('   Citation Style: APA');
        $this->newLine();

        // Example for Management Science Faculty
        $this->info('4. MANAGEMENT SCIENCE FACULTY:');
        $this->info('   Last Chapter: Chapter 5 - "Summary, Conclusion, and Recommendations"');
        $this->info('   Add Section: 5.5 - References');
        $this->info('   Word Count: N/A');
        $this->info('   Citation Style: APA');
        $this->newLine();

        // Example for Medical Faculty
        $this->info('5. MEDICAL FACULTY:');
        $this->info('   Last Chapter: Chapter 6 - "Summary, Conclusion, and Recommendations"');
        $this->info('   Add Section: 6.4 - References');
        $this->info('   Word Count: N/A');
        $this->info('   Citation Style: Vancouver or APA');
        $this->newLine();

        // Example for Law Faculty
        $this->info('6. LAW FACULTY:');
        $this->info('   Last Chapter: Chapter 4 - "Summary, Findings, and Recommendations"');
        $this->info('   Add Section: 4.5 - References');
        $this->info('   Word Count: N/A');
        $this->info('   Citation Style: NALT, OSCOLA, or APA');
        $this->newLine();

        $this->info('Standard References Section Configuration:');
        $this->info('-------------------------------------------');
        $this->info('Section Title: "References"');
        $this->info('Description: "Complete list of all sources cited in the academic work, formatted according to the required citation style."');
        $this->info('Required: true');
        $this->info('Word Count: 0 (not applicable for references)');
        $this->newLine();

        $this->info('Writing Guidance by Faculty:');
        $this->info('LAW: Use NALT/OSCOLA, separate primary and secondary sources, include pinpoint references');
        $this->info('MEDICAL: Use Vancouver/APA, include PMID and DOI, prioritize recent peer-reviewed sources');
        $this->info('ENGINEERING: Use IEEE/APA, include DOI, prioritize technical journals and standards');
        $this->info('SCIENCE: Use APA/journal format, alphabetical by author, peer-reviewed sources');
        $this->info('OTHERS: Use APA/Harvard, alphabetical by author, include DOI/URL for online sources');
        $this->newLine();

        $this->info('The seeder file needs to be updated to include this section in each faculty structure.');
        $this->info('This ensures all new projects will automatically have a References section in their final chapter.');

        return 0;
    }
}
