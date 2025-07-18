import { useState, useEffect, useCallback } from '@wordpress/element';
import { Modal, Button, Flex } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * ConfirmDialog component.
 *
 * Inspired by the (still experimental) ConfirmDialog component from Gutenberg.
 *
 * @link https://developer.wordpress.org/block-editor/reference-guides/components/confirm-dialog/
 *
 * @param {object}      props                   Component props.
 * @param {JSX.Element} props.children          Content of the dialog.
 * @param {boolean}     props.isOpen            Whether the dialog is open or not.
 * @param {string}      props.title             Title of the dialog.
 * @param {function}    props.onConfirm         Callback for when the dialog is confirmed.
 * @param {function}    props.onCancel          Callback for when the dialog is cancelled.
 * @param {string}      props.confirmButtonText Text for the confirm button.
 * @param {string}      props.cancelButtonText  Text for the cancel button.
 *
 * @return {JSX.Element} The ConfirmDialog component.
 */
const ConfirmDialog = (props) => {
  const [isOpen, setIsOpen] = useState(false);

  const {
    children,
    isOpen: isOpenProp,
    title,
    onConfirm,
    onCancel,
    confirmButtonText,
    cancelButtonText,
  } = props;

  useEffect(() => {
    const isOpenSet = typeof isOpenProp !== 'undefined';
    setIsOpen(isOpenSet ? isOpenProp : true);
  }, [isOpenProp]);

  const handleEnter = useCallback((event) => {
    if (event.key === 'Enter') {
      onConfirm();
    }
  }, [onConfirm]);

  if (!isOpen) {
    return <></>;
  }

  return (
    <Modal
      title={title}
      onRequestClose={onCancel}
      onKeyDown={handleEnter}
    >
      <Flex direction="column" justify="space-between" gap={8}>
        {children}
        <Flex direction="row" justify="flex-end">
          <Button
            variant="tertiary"
            onClick={onCancel}
          >
            {cancelButtonText ?? __('Cancel')}
          </Button>
          <Button
            variant="primary"
            onClick={onConfirm}
          >
            {confirmButtonText ?? __('Confirm')}
          </Button>
        </Flex>
      </Flex>
    </Modal>
  );
};

export default ConfirmDialog;
