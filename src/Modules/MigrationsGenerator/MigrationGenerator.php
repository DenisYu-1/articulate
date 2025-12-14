<?php

namespace Articulate\Modules\MigrationsGenerator;

use DateTimeImmutable;

class MigrationGenerator
{
    private string $templatePath;

    private string $outputDirectory;

    public function __construct(string $outputDirectory)
    {
        $this->templatePath = __DIR__ . '/migration_template.php.dist';
        $this->outputDirectory = $outputDirectory;
    }

    public function generate(string $namespace, string $className, string $upScript, string $downScript): void
    {
        // Read the template content
        $templateContent = file_get_contents($this->templatePath);

        $date = new DateTimeImmutable();

        // Replace the placeholders with actual content
        $migrationContent = str_replace(
            ['{{namespace}}', '{{className}}', '{{upScript}}', '{{downScript}}'],
            [$namespace, $className, $upScript, $downScript],
            $templateContent
        );

        $directory = sprintf('%s/%s/%s', $this->outputDirectory, $date->format('Y'), $date->format('m'));
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        $fileName = sprintf('%s/%s.php', $directory, $className);

        // Write the migration content to the specified file
        file_put_contents($fileName, $migrationContent);

        echo "Migration $className generated successfully at $fileName\n";
    }
}
