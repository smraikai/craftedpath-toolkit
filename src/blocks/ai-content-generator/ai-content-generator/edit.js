/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import {
	TextareaControl,
	Button,
	Spinner,
	Notice,
	PanelBody, // Use PanelBody for structure
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Simple Markdown to Gutenberg blocks parser.
 * Handles H1-H6, paragraphs, basic unordered lists, **bold**, and *italic*.
 *
 * @param {string} markdown The Markdown content string.
 * @return {Array} Array of block objects.
 */
function parseMarkdownToBlocks(markdown) {
	if (!markdown) {
		return [];
	}

	// Function to convert inline markdown to HTML
	function convertInlineMarkdown(text) {
		let html = text;
		// Handle **bold** (must be done first)
		html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
		// Handle *italic* (that are not part of bold)
		html = html.replace(/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
		// Handle __bold__ (alternative)
		html = html.replace(/__(.*?)__/g, '<strong>$1</strong>');
		// Handle _italic_ (alternative, avoid affecting __bold__)
		html = html.replace(/(?<!_)_(?!_)(.*?)(?<!_)_(?!_)/g, '<em>$1</em>');
		return html;
	}

	const blocks = [];
	const lines = markdown.trim().split('\n');
	let currentListItems = [];

	lines.forEach((line, index) => {
		const trimmedLine = line.trim();

		// Check for Headers (H1-H6)
		if (trimmedLine.startsWith('#')) {
			// Finish any pending list
			if (currentListItems.length > 0) {
				blocks.push(
					createBlock('core/list', {
						values: currentListItems.join('\n'), // List items already have HTML
					})
				);
				currentListItems = [];
			}

			let level = 0;
			while (trimmedLine[level] === '#') {
				level++;
			}
			if (level > 0 && level <= 6 && trimmedLine[level] === ' ') {
				let content = trimmedLine.substring(level + 1).trim();
				if (content) {
					// Convert inline markdown within the heading
					content = convertInlineMarkdown(content);
					blocks.push(createBlock('core/heading', { level, content }));
				}
				return; // Move to next line
			}
		}

		// Check for Unordered List Items (* or -)
		if (trimmedLine.startsWith('* ') || trimmedLine.startsWith('- ')) {
			let content = trimmedLine.substring(2).trim();
			if (content) {
				// Convert inline markdown within the list item
				content = convertInlineMarkdown(content);
				currentListItems.push(`<li>${content}</li>`); // Store as raw HTML list items
			}
			// If it's the last line, finalize the list
			if (index === lines.length - 1 && currentListItems.length > 0) {
				blocks.push(
					createBlock('core/list', {
						values: currentListItems.join('\n'),
					})
				);
			}
			return; // Move to next line
		}

		// If we encounter a non-list item, finish the current list
		if (currentListItems.length > 0) {
			blocks.push(
				createBlock('core/list', {
					values: currentListItems.join('\n'),
				})
			);
			currentListItems = [];
		}

		// Treat as Paragraph (if not empty)
		if (trimmedLine) {
			// Convert inline markdown within the paragraph
			let content = convertInlineMarkdown(trimmedLine);
			blocks.push(createBlock('core/paragraph', { content }));
		}
		// Ignore empty lines (they acted as paragraph separators implicitly)
	});

	return blocks;
}

/**
 * The edit function describes the structure of your block in the context of the editor.
 */
export default function Edit({ attributes, setAttributes, clientId }) {
	const blockProps = useBlockProps();
	const { insertBlocks } = useDispatch('core/block-editor');

	// Get block index and parent clientId using useSelect
	const { blockIndex, rootClientId } = useSelect(
		(select) => {
			const { getBlockIndex, getBlockRootClientId } =
				select('core/block-editor');
			return {
				blockIndex: getBlockIndex(clientId),
				// Get the clientId of the parent block, or undefined for top-level blocks
				rootClientId: getBlockRootClientId(clientId),
			};
		},
		[clientId] // Dependency array
	);

	// State for prompt, loading status, and notices
	const [prompt, setPrompt] = useState(attributes.prompt || '');
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState(null);
	const [notice, setNotice] = useState(null);

	// Update attribute when prompt state changes
	useEffect(() => {
		setAttributes({ prompt: prompt });
	}, [prompt]);

	// Handle the generate button click
	const handleGenerateClick = () => {
		setIsLoading(true);
		setError(null);
		setNotice(null);

		apiFetch({
			path: '/craftedpath-toolkit/v1/generate-content',
			method: 'POST',
			data: { prompt: prompt },
		})
			.then((response) => {
				console.log('API Response Received:', response);

				if (response.success && response.content) {
					console.log('Raw Content from API:', response.content);
					const generatedBlocks = parseMarkdownToBlocks(
						response.content
					);
					console.log('Parsed Blocks:', generatedBlocks);

					if (generatedBlocks.length > 0) {
						// Calculate the index to insert after the current block
						const insertionIndex = blockIndex + 1;
						console.log(
							`Attempting to insert blocks at index ${insertionIndex} in root ${rootClientId || '(root)'}`
						);

						// Insert blocks at the calculated index within the correct rootClientId (parent)
						insertBlocks(
							generatedBlocks,
							insertionIndex,
							rootClientId,
							false // updateSelection = false
						);

						console.log('insertBlocks dispatch called.');

						setNotice(
							__(
								'Content generated successfully!',
								'ai-content-generator'
							)
						);
						// Optionally clear the prompt after success
						// setPrompt('');
					} else {
						console.error('Parsing resulted in zero blocks.');
						setError(
							__(
								'AI generated content, but it could not be parsed into blocks. Check console for details.',
								'ai-content-generator'
							)
						);
					}
				} else {
					console.error(
						'API response indicated failure or missing content:',
						response
					);
					setError(
						response.message ||
						__(
							'Received an unexpected response from the API.',
							'ai-content-generator'
						)
					);
				}
			})
			.catch((fetchError) => {
				console.error('API Fetch Error:', fetchError);
				setError(
					fetchError.message ||
					__(
						'An error occurred while contacting the AI service.',
						'ai-content-generator'
					)
				);
			})
			.finally(() => {
				setIsLoading(false);
			});
	};

	return (
		<div {...blockProps}>
			<PanelBody title={__('AI Content Generation', 'ai-content-generator')}>
				{error && (
					<Notice status="error" isDismissible={true} onRemove={() => setError(null)}>
						{error}
					</Notice>
				)}
				{notice && (
					<Notice status="success" isDismissible={true} onRemove={() => setNotice(null)}>
						{notice}
					</Notice>
				)}
				<TextareaControl
					label={__('Content Prompt', 'ai-content-generator')}
					help={__(
						'Describe the content you want the AI to generate (e.g., "Write a paragraph about the benefits of WordPress", "Create a heading and two paragraphs about block themes").',
						'ai-content-generator'
					)}
					value={prompt}
					onChange={(newPrompt) => setPrompt(newPrompt)}
					rows={5}
					disabled={isLoading}
				/>
				<Button
					variant="primary"
					onClick={handleGenerateClick}
					isBusy={isLoading}
					disabled={isLoading || !prompt.trim()}
				>
					{isLoading
						? __('Generating...', 'ai-content-generator')
						: __('Generate Content', 'ai-content-generator')}
				</Button>
				{isLoading && <Spinner />}
			</PanelBody>
		</div>
	);
}
