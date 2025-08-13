<?php

declare(strict_types=1);

namespace DR\Review\Ai\Summary;

readonly class TokenAnalysis
{
    /**
     * @param array<string, int>                                           $fileTokenSizes Token count per file (filename => token count)
     * @param array<int, array{file: string, tokens: int, reason: string}> $filteredFiles  Files that were filtered due to token limits
     */
    public function __construct(
        public array $fileTokenSizes = [],
        public array $filteredFiles = []
    ) {
    }

    /**
     * Get token sizes per file.
     * @return array<string, int> Array of filename => token count
     */
    public function getFileTokenSizes(): array
    {
        return $this->fileTokenSizes;
    }

    /**
     * Calculate the total token size across all files.
     */
    public function getTotalTokenSize(): int
    {
        return array_sum($this->fileTokenSizes);
    }

    /**
     * Get the largest file by token count.
     * @return array{filename: string, tokens: int}|null
     */
    public function getLargestFileByTokens(): ?array
    {
        if (count($this->fileTokenSizes) === 0) {
            return null;
        }

        $maxTokens = max($this->fileTokenSizes);
        $filename  = array_search($maxTokens, $this->fileTokenSizes, true);

        return [
            'filename' => $filename,
            'tokens'   => $maxTokens
        ];
    }

    /**
     * Get files sorted by token count (descending).
     * @return array<string, int>
     */
    public function getFilesSortedByTokens(): array
    {
        $sorted = $this->fileTokenSizes;
        arsort($sorted);

        return $sorted;
    }

    /**
     * Get the number of files processed.
     */
    public function getFileCount(): int
    {
        return count($this->fileTokenSizes);
    }

    /**
     * Get files that were filtered due to token limits.
     * @return array<int, array{file: string, tokens: int, reason: string}>
     */
    public function getFilteredFiles(): array
    {
        return $this->filteredFiles;
    }

    /**
     * Get the number of files that were filtered.
     */
    public function getFilteredFileCount(): int
    {
        return count($this->filteredFiles);
    }
}
