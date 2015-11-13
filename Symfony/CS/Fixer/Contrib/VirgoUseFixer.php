<?php

namespace Symfony\CS\Fixer\Contrib;

use Symfony\CS\AbstractFixer;
use Symfony\CS\Tokenizer\Token;
use Symfony\CS\Tokenizer\Tokens;

class VirgoUseFixer extends AbstractFixer {

    /**
     * {@inheritDoc}
     */
    public function fix(\SplFileInfo $file, $content) {
        $tokens = Tokens::fromCode($content);

        $namespacesImports = $tokens->getImportUseIndexes(true);
        $usesOrder = array();

        if (!count($namespacesImports)) {
            return $content;
        }

        foreach ($namespacesImports as $uses) {
            $uses = array_reverse($uses);
            $usesOrder = array_replace($usesOrder, $this->getNewOrder($uses, $tokens));
        }

        // First clean the old content
        // This must be done first as the indexes can be scattered
        foreach ($usesOrder as $use) {
            $tokens->clearRange($use[1], $use[2]);
        }

        $usesOrder = array_reverse($usesOrder, true);

        // Now insert the new tokens, starting from the end
        $lastName = NULL;
        foreach ($usesOrder as $index => $use) {
            $namespaces = explode('\\', $use[0]);
            if ( $lastName !== NULL && $lastName[1] !== $namespaces[0] ) {
                $tokens->insertAt($lastName[0]-2, new Token(array(T_WHITESPACE, PHP_EOL)));
            }
            
            $declarationTokens = Tokens::fromCode('<?php use '.$use[0].';');
            $declarationTokens->clearRange(0, 2); // clear `<?php use `
            $declarationTokens[count($declarationTokens) - 1]->clear(); // clear `;`
            $declarationTokens->clearEmptyTokens();

            $tokens->insertAt($index, $declarationTokens);
            
            $lastName = [$index, $namespaces[0]];
        }

        return $tokens->generateCode();
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription() {
        return 'Virgo Use order fixer';
    }

    private function getNewOrder(array $uses, Tokens $tokens)
    {
        $uses = array_reverse($uses);

        $indexes = array();
        $originalIndexes = array();

        foreach ($uses as $index) {
            $endIndex = $tokens->getNextTokenOfKind($index, array(';'));
            $startIndex = $tokens->getTokenNotOfKindSibling($index + 1, 1, array(array(T_WHITESPACE)));

            $namespace = '';
            $index = $startIndex;

            while ($index <= $endIndex) {
                $token = $tokens[$index];

                if ($index === $endIndex || $token->equals(',')) {
                    $indexes[$startIndex] = array($namespace, $startIndex, $index - 1);
                    $originalIndexes[] = $startIndex;

                    if ($index === $endIndex) {
                        break;
                    }

                    $namespace = '';
                    $nextPartIndex = $tokens->getTokenNotOfKindSibling($index, 1, array(array(','), array(T_WHITESPACE)));
                    $startIndex = $nextPartIndex;
                    $index = $nextPartIndex;

                    continue;
                }

                $namespace .= $token->getContent();
                ++$index;
            }
        }

        $i = -1;

        $indexes = $this->doOrder($indexes);
        
        $usesOrder = array();

        // Loop trough the index but use original index order
        foreach ($indexes as $v) {
            $usesOrder[$originalIndexes[++$i]] = $v;
        }

        return $usesOrder;
    }

    /**
     * @param $indexes
     *
     * @return mixed
     */
    private function doOrder($indexes) {
        
        $virgo = $ed = $octo = $shared = $others = [];
        foreach ($indexes as $v) {
            $namespaces = explode('\\', $v[0]);
            switch (TRUE) {
                case 'Virgo' === $namespaces[0]:
                    $virgo[$v[0]] = $v;
                    break;
                case 'EdigitalShared' === $namespaces[0]:
                    $shared[$v[0]] = $v;
                    break;
                case preg_match('#^Edigital#', $namespaces[0]):
                    $ed[$v[0]] = $v;
                    break;
                case 'OctoPlus' === $namespaces[0]:
                    $octo[$v[0]] = $v;
                    break;
                default:
                    $others[$v[0]] = $v;
                    break;
            }
        }

        ksort($others);
        ksort($virgo);
        ksort($ed);
        ksort($octo);
        ksort($shared);
        
        return array_merge(
            array_values($others),
            array_values($virgo),
            array_values($ed),
            array_values($octo),
            array_values($shared)
        );
    }
}