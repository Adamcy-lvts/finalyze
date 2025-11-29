export interface ContentAnalysis {
  word_count: number
  citation_count: number
  table_count: number
  figure_count: number
  claim_count: number
  has_introduction: boolean
  has_conclusion: boolean
  detected_issues: string[]
  quality_metrics: {
    avg_sentence_length: number
    paragraph_count: number
    reading_level: string
  }
}

export class ContentAnalyzer {
  /**
   * Analyze chapter content from editor
   */
  static analyze(editorContent: string): ContentAnalysis {
    const parser = new DOMParser()
    const doc = parser.parseFromString(editorContent, 'text/html')

    return {
      word_count: this.countWords(doc),
      citation_count: this.countCitations(doc),
      table_count: this.countTables(doc),
      figure_count: this.countFigures(doc),
      claim_count: this.countClaims(doc),
      has_introduction: this.hasIntroduction(doc),
      has_conclusion: this.hasConclusion(doc),
      detected_issues: this.detectIssues(doc),
      quality_metrics: this.calculateQualityMetrics(doc),
    }
  }

  private static countWords(doc: Document): number {
    const text = doc.body.textContent || ''
    return text.trim().split(/\s+/).filter((word) => word.length > 0).length
  }

  private static countCitations(doc: Document): number {
    // Look for citation patterns: (Author, Year), [1], etc.
    const text = doc.body.textContent || ''
    const patterns = [
      /\([A-Z][a-z]+,?\s+\d{4}\)/g, // (Author, 2024)
      /\[[0-9]+\]/g, // [1]
      /\([A-Z][a-z]+\s+et al\.,?\s+\d{4}\)/g, // (Author et al., 2024)
    ]

    let count = 0
    patterns.forEach((pattern) => {
      const matches = text.match(pattern)
      if (matches) count += matches.length
    })

    // Also check for <cite> or custom citation elements
    count += doc.querySelectorAll('cite, [data-citation]').length

    return count
  }

  private static countTables(doc: Document): number {
    return doc.querySelectorAll('table').length
  }

  private static countFigures(doc: Document): number {
    return doc.querySelectorAll('img, figure').length
  }

  private static countClaims(doc: Document): number {
    // Detect claim-like statements (sentences with strong assertions)
    const text = doc.body.textContent || ''
    const sentences = text.split(/[.!?]+/)

    const claimKeywords = [
      'demonstrates',
      'proves',
      'shows',
      'indicates',
      'reveals',
      'confirms',
      'establishes',
      'suggests',
      'implies',
      'evidences',
    ]

    return sentences.filter((sentence) =>
      claimKeywords.some((keyword) => sentence.toLowerCase().includes(keyword)),
    ).length
  }

  private static hasIntroduction(doc: Document): boolean {
    const text = (doc.body.textContent || '').toLowerCase()
    const introKeywords = ['introduction', 'background', 'overview']
    return introKeywords.some((keyword) => text.includes(keyword))
  }

  private static hasConclusion(doc: Document): boolean {
    const text = (doc.body.textContent || '').toLowerCase()
    const conclusionKeywords = ['conclusion', 'summary', 'in summary']
    return conclusionKeywords.some((keyword) => text.includes(keyword))
  }

  private static detectIssues(doc: Document): string[] {
    const issues: string[] = []
    const text = doc.body.textContent || ''

    // Check for claims without nearby citations
    const claimCount = this.countClaims(doc)
    const citationCount = this.countCitations(doc)

    if (claimCount > 0 && citationCount === 0) {
      issues.push('claims_without_evidence')
    }

    if (claimCount > citationCount * 2) {
      issues.push('insufficient_citations')
    }

    // Check for methodology chapter without tables
    const hasMethodology = text.toLowerCase().includes('methodology')
    if (hasMethodology && this.countTables(doc) === 0) {
      issues.push('insufficient_tables')
    }

    // Check for weak argument indicators
    const weakWords = ['maybe', 'might', 'could possibly', 'seems to']
    const hasWeakLanguage = weakWords.some((word) => text.toLowerCase().includes(word))
    if (hasWeakLanguage && claimCount > 3) {
      issues.push('weak_arguments')
    }

    // Check word count
    const wordCount = this.countWords(doc)
    if (wordCount < 100) {
      issues.push('insufficient_content')
    }

    return issues
  }

  private static calculateQualityMetrics(doc: Document): ContentAnalysis['quality_metrics'] {
    const text = doc.body.textContent || ''
    const sentences = text.split(/[.!?]+/).filter((s) => s.trim().length > 0)
    const paragraphs = doc.querySelectorAll('p').length

    const avgSentenceLength = sentences.length > 0 ? this.countWords(doc) / sentences.length : 0

    return {
      avg_sentence_length: Math.round(avgSentenceLength),
      paragraph_count: paragraphs,
      reading_level:
        avgSentenceLength > 25 ? 'complex' : avgSentenceLength > 15 ? 'moderate' : 'simple',
    }
  }
}
