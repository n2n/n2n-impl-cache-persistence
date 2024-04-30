<?php

namespace n2n\impl\cache\persistence;

enum PdoCacheDataSize {
	/**
	 * Gets translated to a VARCHAR column
	 */
	case STRING;
	/**
	 * Gets translated to a TEXT column
	 */
	case TEXT;
}